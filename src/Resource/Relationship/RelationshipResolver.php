<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Relationship;

use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Error\ErrorObject;
use AlexFigures\Symfony\Http\Error\ErrorSource;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Http\Exception\ValidationException;
use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Metadata\RelationshipSemantics;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\InverseSideMapping;
use Doctrine\ORM\Mapping\OwningSideMapping;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Doctrine-backed RelationshipResolver that:
 * - validates type/targetClass
 * - supports VERIFY/REFERENCE policies
 * - supports MERGE/REPLACE semantics (diff-based)
 * - keeps owning/inverse sides in sync
 * - never replaces Doctrine collections (no clear()+set array)
 * - produces JSON:API pointers for precise client errors
 */
class RelationshipResolver
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ResourceRegistryInterface $registry,
        private readonly PropertyAccessorInterface $accessor,
        private readonly ?ErrorMapper $errors = null,
        private readonly bool $errorOnUnknownRelationship = false,
    ) {
    }

    /**
     * @param  array<string, mixed> $relationshipsPayload JSON:API relationships member ("relationships": { ... })
     * @param  array<string, mixed> $context
     * @throws ValidationException
     */
    public function applyRelationships(
        object $entity,
        array $relationshipsPayload,
        ResourceMetadata $resourceMetadata,
        bool $isCreate,
        array $context = []
    ): void {
        $errorBucket = [];

        $ownerEm = $this->getEntityManagerForClass($resourceMetadata->getDataClass());

        foreach ($relationshipsPayload as $relName => $relBody) {
            $relMeta = $resourceMetadata->relationships[$relName] ?? null;

            if (!$relMeta) {
                if ($this->errorOnUnknownRelationship) {
                    $errorBucket[] = $this->createValidationError(
                        $this->pointerRelationships($relName),
                        sprintf('Unknown relationship "%s".', $relName),
                        ['relationship' => $relName]
                    );
                }
                continue;
            }

            if (!$relMeta->isWritable($isCreate)) {
                $errorBucket[] = $this->createValidationError(
                    $this->pointerRelationships($relName),
                    'Relationship is not writable for this operation.',
                    ['writableOnCreate' => $relMeta->writableOnCreate, 'writableOnUpdate' => $relMeta->writableOnUpdate]
                );
                continue;
            }

            // Basic structure validation
            if (!\is_array($relBody) || !\array_key_exists('data', $relBody)) {
                $errorBucket[] = $this->createValidationError(
                    $this->pointerRelationships($relName),
                    'Invalid relationship payload: expected an object with a "data" member.'
                );
                continue;
            }

            $data = $relBody['data'];

            try {
                if ($data === null) {
                    if ($relMeta->toMany) {
                        $errorBucket[] = $this->createValidationError(
                            $this->pointerRelationships($relName),
                            'Null is not allowed for to-many relationships. Use an empty array to clear items.',
                        );
                        continue;
                    }
                    $this->syncToOne($ownerEm, $entity, $resourceMetadata, $relMeta, null);
                    continue;
                }

                if (\is_array($data) && isset($data['type'])) {
                    // to-one
                    $ri = $this->validateResourceIdentifier($relName, $data, $relMeta);
                    $target = $this->resolveTarget($ownerEm, $resourceMetadata, $ri['type'], $ri['id'], $relMeta);
                    $this->syncToOne($ownerEm, $entity, $resourceMetadata, $relMeta, $target);
                    continue;
                }

                if (\is_array($data) && $this->isList($data)) {
                    // to-many
                    /** @var list<array{type:string,id:string}> $list */
                    $list = [];
                    foreach ($data as $i => $item) {
                        if (!\is_array($item) || !isset($item['type'], $item['id'])) {
                            $errorBucket[] = $this->createValidationError(
                                $this->pointerRelationshipsIndex($relName, (int)$i),
                                'Each array element must be a resource identifier object with "type" and "id".'
                            );
                            continue;
                        }
                        try {
                            $ri = $this->validateResourceIdentifier($relName, $item, $relMeta, (int)$i);
                            $list[] = $ri;
                        } catch (ValidationException $ve) {
                            foreach ($ve->getErrors() as $e) {
                                $errorBucket[] = $e;
                            }
                        }
                    }

                    if (!empty($errorBucket)) {
                        // fall through to throwing below
                    } else {
                        $this->syncToMany($ownerEm, $entity, $resourceMetadata, $relMeta, $list);
                    }
                    continue;
                }

                $errorBucket[] = $this->createValidationError(
                    $this->pointerRelationships($relName),
                    'Invalid relationship payload structure.'
                );
            } catch (ValidationException $e) {
                foreach ($e->getErrors() as $err) {
                    $errorBucket[] = $err;
                }
            }
        }

        if ($errorBucket) {
            throw new ValidationException($errorBucket);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>         $ri
     * @return array{type:string,id:string}
     * @throws ValidationException
     */
    private function validateResourceIdentifier(
        string $relName,
        array $ri,
        RelationshipMetadata $meta,
        ?int $index = null
    ): array {
        $errors = [];
        $ptrBase = $this->pointerRelationships($relName);

        if (!array_key_exists('type', $ri)) {
            $errors[] = $this->createValidationError($ptrBase, 'Missing "type" in resource identifier.');
        }
        if (!array_key_exists('id', $ri) || $ri['id'] === '' || $ri['id'] === null) {
            $errors[] = $this->createValidationError(
                $index === null ? $ptrBase : $this->pointerRelationshipsIndex($relName, $index),
                'Missing "id" in resource identifier.'
            );
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $typeValue = $ri['type'];
        if (!\is_string($typeValue)) {
            throw new ValidationException([
                $this->createValidationError(
                    $index === null ? $ptrBase.'/type' : $this->pointerRelationshipsIndex($relName, $index).'/type',
                    'Resource identifier "type" must be a string.'
                ),
            ]);
        }

        $rawId = $ri['id'];
        if (!\is_string($rawId) && !\is_int($rawId) && !($rawId instanceof Stringable)) {
            throw new ValidationException([
                $this->createValidationError(
                    $index === null ? $ptrBase.'/id' : $this->pointerRelationshipsIndex($relName, $index).'/id',
                    'Resource identifier "id" must be a string or stringable value.'
                ),
            ]);
        }

        $type = $typeValue;
        $id = (string)$rawId;

        if ($meta->targetType !== null && $meta->targetType !== $type) {
            $errors[] = $this->createValidationError(
                $index === null ? $ptrBase.'/type' : $this->pointerRelationshipsIndex($relName, $index).'/type',
                sprintf('Invalid type: expected "%s", got "%s".', $meta->targetType, $type),
                ['expected' => $meta->targetType, 'actual' => $type]
            );
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        // Validate class compatibility if targetClass provided
        // Skip this validation for to-many relationships where targetClass is Collection
        if ($meta->targetClass !== null && !is_a($meta->targetClass, Collection::class, true)) {
            $reg = $this->registry->getByType($type);

            // Resolve "self", "static", and "parent" keywords to actual class names
            $expectedClass = $meta->targetClass;
            if ($expectedClass === 'self' || $expectedClass === 'static') {
                // For self-referential relationships, the target class should match the source entity class
                // We can infer this from the targetType if it matches the current resource type
                if ($meta->targetType !== null && $this->registry->hasType($meta->targetType)) {
                    $expectedClass = $this->registry->getByType($meta->targetType)->getDataClass();
                }
            }

            $resolvedClass = $reg->getDataClass();

            if (!\is_a($resolvedClass, $expectedClass, true)) {
                throw new ValidationException([
                    $this->createValidationError(
                        $index === null ? $ptrBase.'/type' : $this->pointerRelationshipsIndex($relName, $index).'/type',
                        sprintf('Type "%s" is not compatible with expected class "%s".', $type, $expectedClass),
                        ['resolvedClass' => $resolvedClass]
                    )
                ]);
            }
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Resolves target entity by policy.
     * @throws NotFoundException   When related resource is not found (404, not 422)
     * @throws ValidationException For other validation errors
     */
    private function resolveTarget(
        EntityManagerInterface $ownerEm,
        ResourceMetadata $ownerMeta,
        string $type,
        string $id,
        RelationshipMetadata $meta
    ): object {
        $reg = $this->registry->getByType($type);
        $class = $reg->getDataClass();

        $targetEm = $this->getEntityManagerForClass($class);
        $this->assertCompatibleEntityManagers($ownerEm, $targetEm, $ownerMeta->getDataClass(), $class);

        if ($meta->linkingPolicy === RelationshipLinkingPolicy::REFERENCE) {
            $reference = $targetEm->getReference($class, $id);

            return $this->expectObject(
                $reference,
                sprintf('Doctrine reference for "%s" returned a non-object result.', $class)
            );
        }

        // VERIFY policy: early existence check with good error message
        $obj = $targetEm->find($class, $id);
        if (!\is_object($obj)) {
            // JSON:API spec requires 404 for missing related resources, not 422
            $error = $this->errors?->notFound(
                sprintf('Related resource of type "%s" with id "%s" was not found.', $type, $id),
                '/data/relationships'
            ) ?? new ErrorObject(
                id: null,
                aboutLink: null,
                status: '404',
                code: 'resource-not-found',
                title: 'Resource Not Found',
                detail: sprintf('Related resource of type "%s" with id "%s" was not found.', $type, $id),
                source: new ErrorSource(pointer: '/data/relationships'),
                meta: ['type' => $type, 'id' => $id]
            );

            throw new NotFoundException(
                sprintf('Related resource of type "%s" with id "%s" was not found.', $type, $id),
                [$error]
            );
        }
        $entity = $this->expectObject($obj, sprintf('Doctrine find for "%s" returned a non-object result.', $class));

        return $entity;
    }

    /**
     * @param  list<array{type: string, id: string}> $resourceIdentifiers
     * @return array<string, object>
     */
    private function resolveTargetsBatch(
        EntityManagerInterface $ownerEm,
        ResourceMetadata $ownerMeta,
        array $resourceIdentifiers,
        RelationshipMetadata $meta
    ): array {
        if ($resourceIdentifiers === []) {
            return [];
        }

        /** @var array<string, array<int, string>> $byType */
        $byType = [];
        foreach ($resourceIdentifiers as $idx => $ri) {
            $byType[$ri['type']][$idx] = $ri['id'];
        }

        /** @var array<string, object> $resolved */
        $resolved = [];
        $errors = [];

        foreach ($byType as $type => $idsWithIndex) {
            $reg = $this->registry->getByType($type);
            $class = $reg->getDataClass();
            $targetEm = $this->getEntityManagerForClass($class);
            $this->assertCompatibleEntityManagers($ownerEm, $targetEm, $ownerMeta->getDataClass(), $class);
            /** @var list<string> $ids */
            $ids = array_values($idsWithIndex);

            if ($meta->linkingPolicy === RelationshipLinkingPolicy::REFERENCE) {
                foreach ($idsWithIndex as $idx => $id) {
                    $resolved[$id] = $this->expectObject(
                        $targetEm->getReference($class, $id),
                        sprintf('Doctrine reference for "%s" returned a non-object.', $class)
                    );
                }
                continue;
            }

            $qb = $targetEm->createQueryBuilder();
            $qb->select('e')
                ->from($class, 'e')
                ->where($qb->expr()->in('e.id', ':ids'))
                ->setParameter('ids', $ids);

            /** @var list<object> $entities */
            $entities = $qb->getQuery()->getResult();

            $foundById = [];
            $uow = $targetEm->getUnitOfWork();
            foreach ($entities as $entity) {
                $entity = $this->expectObject($entity, sprintf('Doctrine query for "%s" returned a non-object result.', $class));
                $identifier = $uow->getSingleIdentifierValue($entity);
                $id = $this->stringifyIdentifier($identifier, $entity);
                $foundById[$id] = $entity;
                $resolved[$id] = $entity;
            }

            foreach ($idsWithIndex as $idx => $id) {
                if (isset($foundById[$id])) {
                    continue;
                }

                $error = $this->errors?->notFound(
                    sprintf('Related resource of type "%s" with id "%s" was not found.', $type, $id),
                    $this->pointerRelationshipsIndex($meta->name, $idx).'/id'
                ) ?? new ErrorObject(
                    id: null,
                    aboutLink: null,
                    status: '404',
                    code: 'resource-not-found',
                    title: 'Resource Not Found',
                    detail: sprintf('Related resource of type "%s" with id "%s" was not found.', $type, $id),
                    source: new ErrorSource(pointer: $this->pointerRelationshipsIndex($meta->name, $idx).'/id'),
                    meta: ['type' => $type, 'id' => $id]
                );

                $errors[] = $error;
            }
        }

        if ($errors !== []) {
            throw new NotFoundException('One or more related resources were not found.', $errors);
        }

        return $resolved;
    }

    private function syncToOne(
        EntityManagerInterface $em,
        object $owner,
        ResourceMetadata $ownerMeta,
        RelationshipMetadata $relMeta,
        ?object $target
    ): void {
        $field = $relMeta->propertyPath ?? $relMeta->name;
        $cm = $em->getClassMetadata($ownerMeta->getDataClass());
        if (!$cm->hasAssociation($field)) {
            // fall back to accessor set (non-association field?)
            $this->accessor->setValue($owner, $field, $target);
            return;
        }

        $assoc = $cm->getAssociationMapping($field);

        // nullability check
        if ($target === null && $relMeta->nullable === false) {
            throw new ValidationException([
                $this->createValidationError(
                    $this->pointerRelationships($relMeta->name),
                    'This relationship cannot be null.'
                )
            ]);
        }

        // Set on owning side
        if ($assoc instanceof OwningSideMapping) {
            $this->callSetter($owner, $field, $target);

            // keep inverse in sync when inversedBy defined
            if ($assoc->inversedBy !== null && $target !== null) {
                $this->addInverse($em, $target, $assoc->inversedBy, $owner);
            }
            return;
        }

        // Inverse side: set on target owning side if mappedBy available
        if ($assoc instanceof InverseSideMapping && $target !== null) {
            $owningField = $assoc->mappedBy;
            $this->addInverse($em, $target, $owningField, $owner);
            // also update inverse field on owner for in-memory consistency
            $this->callSetter($owner, $field, $target);
            return;
        }

        // Fallback: direct set
        $this->callSetter($owner, $field, $target);
    }

    /**
     * @param list<array{type:string,id:string}> $desired
     */
    private function syncToMany(
        EntityManagerInterface $em,
        object $owner,
        ResourceMetadata $ownerMeta,
        RelationshipMetadata $relMeta,
        array $desired
    ): void {
        $field = $relMeta->propertyPath ?? $relMeta->name;

        // Ensure collection exists
        $collection = $this->getOrInitCollection($owner, $field);

        // Build current id set
        $uow = $em->getUnitOfWork();
        /** @var array<string, object> $currentById */
        $currentById = [];
        foreach ($collection as $e) {
            $entity = $this->expectObject($e, sprintf('Collection "%s" on %s must contain only objects.', $field, $owner::class));
            $identifier = $uow->getSingleIdentifierValue($entity);
            $id = $this->stringifyIdentifier($identifier, $entity);
            $currentById[$id] = $entity;
        }

        // Build desired set using batch resolution (eliminates N+1 query problem)
        // This resolves all entities in a single query instead of N individual queries
        $desiredById = $this->resolveTargetsBatch($em, $ownerMeta, $desired, $relMeta);

        // Determine adds/removes by semantics
        /** @var array<string, object> $adds */
        $adds = [];
        foreach ($desiredById as $id => $obj) {
            if (!isset($currentById[$id])) {
                $adds[$id] = $obj;
            }
        }
        /** @var array<string, object> $removes */
        $removes = [];
        if ($relMeta->semantics === RelationshipSemantics::REPLACE) {
            foreach ($currentById as $id => $obj) {
                if (!isset($desiredById[$id])) {
                    $removes[$id] = $obj;
                }
            }
        }

        // Apply removes first
        foreach ($removes as $id => $obj) {
            $this->removeLink($em, $owner, $ownerMeta, $field, $obj);
        }
        // Apply adds
        foreach ($adds as $id => $obj) {
            $this->addLink($em, $owner, $ownerMeta, $field, $obj);
        }

        // Cardinality validation
        $count = $this->getOrInitCollection($owner, $field)->count();
        if ($relMeta->minItems !== null && $count < $relMeta->minItems) {
            throw new ValidationException([
                $this->createValidationError(
                    $this->pointerRelationships($relMeta->name),
                    sprintf('Relationship must contain at least %d item(s).', $relMeta->minItems)
                )
            ]);
        }
        if ($relMeta->maxItems !== null && $count > $relMeta->maxItems) {
            throw new ValidationException([
                $this->createValidationError(
                    $this->pointerRelationships($relMeta->name),
                    sprintf('Relationship must contain at most %d item(s).', $relMeta->maxItems)
                )
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Low-level utilities
    // ─────────────────────────────────────────────────────────────────────────────

    private function addLink(
        EntityManagerInterface $em,
        object $owner,
        ResourceMetadata $ownerMeta,
        string $field,
        object $target
    ): void {
        $cm = $em->getClassMetadata($ownerMeta->getDataClass());
        if ($cm->hasAssociation($field)) {
            $assoc = $cm->getAssociationMapping($field);

            // Try domain adder on owner
            $adder = $this->guessAdderName($field);
            if (method_exists($owner, $adder)) {
                $owner->{$adder}($target);
                return;
            }

            // Owning side: mutate owner's collection
            if ($assoc instanceof OwningSideMapping) {
                $col = $this->getOrInitCollection($owner, $field);
                if (!$col->contains($target)) {
                    $col->add($target);
                }

                // keep inverse in sync when inversedBy exists
                if ($assoc->inversedBy !== null) {
                    $this->addInverse($em, $target, $assoc->inversedBy, $owner);
                }
                return;
            }

            // Inverse side: update owning side on target
            if ($assoc instanceof InverseSideMapping) {
                $owningField = $assoc->mappedBy;
                $this->addInverse($em, $target, $owningField, $owner);
                // and mirror locally for consistency
                $this->getOrInitCollection($owner, $field)->add($target);
                return;
            }
        }

        // Fallback: treat as a simple collection field
        $col = $this->getOrInitCollection($owner, $field);
        if (!$col->contains($target)) {
            $col->add($target);
        }
    }

    private function removeLink(
        EntityManagerInterface $em,
        object $owner,
        ResourceMetadata $ownerMeta,
        string $field,
        object $target
    ): void {
        $cm = $em->getClassMetadata($ownerMeta->getDataClass());
        if ($cm->hasAssociation($field)) {
            $assoc = $cm->getAssociationMapping($field);

            // Try domain remover on owner
            $remover = $this->guessRemoverName($field);
            if (method_exists($owner, $remover)) {
                $owner->{$remover}($target);
                return;
            }

            if ($assoc instanceof OwningSideMapping) {
                $this->getOrInitCollection($owner, $field)->removeElement($target);
                if ($assoc->inversedBy !== null) {
                    $this->removeInverse($em, $target, $assoc->inversedBy, $owner);
                }
                return;
            }

            if ($assoc instanceof InverseSideMapping) {
                $this->removeInverse($em, $target, $assoc->mappedBy, $owner);
                $this->getOrInitCollection($owner, $field)->removeElement($target);
                return;
            }
        }

        // Fallback
        $this->getOrInitCollection($owner, $field)->removeElement($target);
    }

    private function addInverse(EntityManagerInterface $em, object $target, string $owningField, object $owner): void
    {
        $assoc = $this->resolveAssociation($em, $target, $owningField);

        if ($assoc !== null && $assoc->isToMany()) {
            $adder = $this->guessAdderName($owningField);
            if (method_exists($target, $adder)) {
                $target->{$adder}($owner);
                return;
            }

            $this->getOrInitCollection($target, $owningField)->add($owner);
            return;
        }

        $this->callSetter($target, $owningField, $owner);
    }

    private function removeInverse(EntityManagerInterface $em, object $target, string $field, object $owner): void
    {
        $assoc = $this->resolveAssociation($em, $target, $field);

        if ($assoc !== null && $assoc->isToMany()) {
            $remover = $this->guessRemoverName($field);
            if (method_exists($target, $remover)) {
                $target->{$remover}($owner);
                return;
            }

            $this->getOrInitCollection($target, $field)->removeElement($owner);
            return;
        }

        $current = $this->accessor->getValue($target, $field);
        if ($current === $owner) {
            $this->callSetter($target, $field, null);
        }
    }

    private function callSetter(object $obj, string $field, mixed $value): void
    {
        $setter = 'set'.\ucfirst($field);
        if (\method_exists($obj, $setter)) {
            $obj->{$setter}($value);
            return;
        }
        $this->accessor->setValue($obj, $field, $value);
    }

    /**
     * @return Collection<int, object>
     */
    private function getOrInitCollection(object $obj, string $field): Collection
    {
        $val = $this->accessor->getValue($obj, $field);
        if ($val instanceof Collection) {
            return $this->assertObjectCollection($val, $obj::class, $field);
        }
        $col = new ArrayCollection();
        $this->accessor->setValue($obj, $field, $col);

        return $this->assertObjectCollection($col, $obj::class, $field);
    }

    /**
     * @param array<mixed> $arr
     */
    private function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_is_list($arr);
    }

    private function guessAdderName(string $field): string
    {
        return 'add'.\ucfirst($this->singularize($field));
    }

    private function guessRemoverName(string $field): string
    {
        return 'remove'.\ucfirst($this->singularize($field));
    }

    private function singularize(string $name): string
    {
        // naive best-effort singularization without intl/inflector dependency
        return str_ends_with($name, 's') ? substr($name, 0, -1) : $name;
    }

    private function pointerRelationships(string $relName): string
    {
        return '/data/relationships/'.$relName.'/data';
    }

    private function pointerRelationshipsIndex(string $relName, int $i): string
    {
        return '/data/relationships/'.$relName.'/data/'.$i;
    }

    /**
     * Create validation error using ErrorMapper if available, otherwise create ErrorObject directly.
     *
     * @param array<string, mixed> $meta
     */
    private function createValidationError(string $pointer, string $detail, array $meta = []): ErrorObject
    {
        if ($this->errors !== null) {
            return $this->errors->validationError($pointer, $detail, $meta);
        }

        return new ErrorObject(
            id: null,
            aboutLink: null,
            status: '422',
            code: 'validation_error',
            title: 'Validation Error',
            detail: $detail,
            source: new ErrorSource(pointer: $pointer),
            meta: $meta
        );
    }

    /**
     * @param class-string $class
     */
    private function getEntityManagerForClass(string $class): EntityManagerInterface
    {
        $em = $this->managerRegistry->getManagerForClass($class);

        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException(sprintf('No Doctrine ORM entity manager registered for class "%s".', $class));
        }

        return $em;
    }

    private function assertCompatibleEntityManagers(
        EntityManagerInterface $ownerEm,
        EntityManagerInterface $targetEm,
        string $ownerClass,
        string $targetClass
    ): void {
        if ($ownerEm === $targetEm) {
            return;
        }

        throw new ValidationException([
            $this->createValidationError(
                '/data/relationships',
                sprintf(
                    'Entity managers for "%s" and "%s" differ. Cross-entity-manager relationships are not supported.',
                    $ownerClass,
                    $targetClass
                )
            ),
        ]);
    }

    /**
     * @param mixed $value
     */
    private function expectObject(mixed $value, string $context): object
    {
        if (!\is_object($value)) {
            throw new RuntimeException($context);
        }

        return $value;
    }

    /**
     * @param  Collection<int, mixed>  $collection
     * @return Collection<int, object>
     */
    private function assertObjectCollection(Collection $collection, string $class, string $field): Collection
    {
        foreach ($collection as $value) {
            if (!\is_object($value)) {
                throw new RuntimeException(sprintf('Collection "%s" on %s must contain only objects.', $field, $class));
            }
        }

        /** @var Collection<int, object> $collection */
        return $collection;
    }

    private function stringifyIdentifier(mixed $identifier, object $entity): string
    {
        if ($identifier instanceof Stringable) {
            return (string) $identifier;
        }

        if (\is_string($identifier) || \is_int($identifier)) {
            return (string) $identifier;
        }

        throw new RuntimeException(sprintf(
            'Unable to normalize identifier for entity "%s". Only scalar or Stringable identifiers are supported.',
            $entity::class
        ));
    }

    private function resolveAssociation(EntityManagerInterface $em, object $entity, string $field): ?AssociationMapping
    {
        $metadata = $em->getClassMetadata($entity::class);

        if (!$metadata->hasAssociation($field)) {
            return null;
        }

        return $metadata->getAssociationMapping($field);
    }
}
