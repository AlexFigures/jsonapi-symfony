<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Relationship;

use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Error\ErrorObject;
use AlexFigures\Symfony\Http\Error\ErrorSource;
use AlexFigures\Symfony\Http\Exception\ValidationException;
use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Metadata\RelationshipSemantics;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
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
        private readonly EntityManagerInterface $em,
        private readonly ResourceRegistryInterface $registry,
        private readonly PropertyAccessorInterface $accessor,
        private readonly ?ErrorMapper $errors = null,
        private readonly bool $errorOnUnknownRelationship = false,
    ) {
    }

    /**
     * @param  array<string, mixed> $relationshipsPayload JSON:API relationships member ("relationships": { ... })
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
                    $this->syncToOne($entity, $resourceMetadata, $relMeta, null);
                    continue;
                }

                if (\is_array($data) && isset($data['type'])) {
                    // to-one
                    $ri = $this->validateResourceIdentifier($relName, $data, $relMeta);
                    $target = $this->resolveTarget($ri['type'], $ri['id'], $relMeta);
                    $this->syncToOne($entity, $resourceMetadata, $relMeta, $target);
                    continue;
                }

                if (\is_array($data) && $this->isList($data)) {
                    // to-many
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
                        $this->syncToMany($entity, $resourceMetadata, $relMeta, $list);
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

        if (!isset($ri['type'])) {
            $errors[] = $this->createValidationError($ptrBase, 'Missing "type" in resource identifier.');
        }
        if (!isset($ri['id']) || $ri['id'] === '' || $ri['id'] === null) {
            $errors[] = $this->createValidationError(
                $index === null ? $ptrBase : $this->pointerRelationshipsIndex($relName, $index),
                'Missing "id" in resource identifier.'
            );
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $type = (string)$ri['type'];
        $id = (string)$ri['id'];

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
        if ($meta->targetClass !== null && !is_a($meta->targetClass, \Doctrine\Common\Collections\Collection::class, true)) {
            $reg = $this->registry->getByType($type);

            // Resolve "self", "static", and "parent" keywords to actual class names
            $expectedClass = $meta->targetClass;
            if ($expectedClass === 'self' || $expectedClass === 'static') {
                // For self-referential relationships, the target class should match the source entity class
                // We can infer this from the targetType if it matches the current resource type
                if ($meta->targetType !== null && $this->registry->hasType($meta->targetType)) {
                    $expectedClass = $this->registry->getByType($meta->targetType)->dataClass;
                }
            }

            if (!\is_a($reg->dataClass, $expectedClass, true)) {
                throw new ValidationException([
                    $this->createValidationError(
                        $index === null ? $ptrBase.'/type' : $this->pointerRelationshipsIndex($relName, $index).'/type',
                        sprintf('Type "%s" is not compatible with expected class "%s".', $type, $expectedClass),
                        ['resolvedClass' => $reg->dataClass]
                    )
                ]);
            }
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Resolves target entity by policy.
     * @throws ValidationException
     */
    private function resolveTarget(string $type, string $id, RelationshipMetadata $meta): object
    {
        $reg = $this->registry->getByType($type);
        $class = $reg->dataClass;

        if ($meta->linkingPolicy === RelationshipLinkingPolicy::REFERENCE) {
            return $this->em->getReference($class, $id);
        }

        // VERIFY policy: early existence check with good error message
        $obj = $this->em->find($class, $id);
        if (!$obj) {
            throw new ValidationException([
                $this->createValidationError(
                    // pointer will be completed by caller (adds /id for element)
                    '/data/relationships',
                    sprintf('Related resource of type "%s" with id "%s" was not found.', $type, $id),
                    ['type' => $type, 'id' => $id]
                )
            ]);
        }
        return $obj;
    }

    /**
     * Resolves multiple target entities in a single batch query.
     * This eliminates the N+1 query problem for to-many relationships.
     *
     * @param  array<int, array{type: string, id: string}> $resourceIdentifiers Array of resource identifiers
     * @param  RelationshipMetadata                        $meta                Relationship metadata
     * @return array<string, object>                       Map of ID => entity
     * @throws ValidationException                         If any entities are not found (VERIFY policy only)
     */
    private function resolveTargetsBatch(array $resourceIdentifiers, RelationshipMetadata $meta): array
    {
        if (empty($resourceIdentifiers)) {
            return [];
        }

        // Group by type (in case of polymorphic relationships)
        $byType = [];
        foreach ($resourceIdentifiers as $idx => $ri) {
            $byType[$ri['type']][$idx] = $ri['id'];
        }

        $resolved = [];
        $errors = [];

        foreach ($byType as $type => $idsWithIndex) {
            $reg = $this->registry->getByType($type);
            $class = $reg->dataClass;
            $ids = array_values($idsWithIndex);

            if ($meta->linkingPolicy === RelationshipLinkingPolicy::REFERENCE) {
                // REFERENCE policy: create proxies for all IDs (no database query)
                foreach ($idsWithIndex as $idx => $id) {
                    $resolved[$id] = $this->em->getReference($class, $id);
                }
            } else {
                // VERIFY policy: batch fetch all entities with single query
                $qb = $this->em->createQueryBuilder();
                $qb->select('e')
                    ->from($class, 'e')
                    ->where($qb->expr()->in('e.id', ':ids'))
                    ->setParameter('ids', $ids);

                $entities = $qb->getQuery()->getResult();

                // Index by ID
                $foundById = [];
                $uow = $this->em->getUnitOfWork();
                foreach ($entities as $entity) {
                    $id = (string)$uow->getSingleIdentifierValue($entity);
                    $foundById[$id] = $entity;
                    $resolved[$id] = $entity;
                }

                // Check for missing entities and create precise error pointers
                foreach ($idsWithIndex as $idx => $id) {
                    if (!isset($foundById[$id])) {
                        $errors[] = new ErrorObject(
                            id: null,
                            aboutLink: null,
                            status: '422',
                            code: 'validation_error',
                            title: 'Validation Error',
                            detail: sprintf('Related resource of type "%s" with id "%s" was not found.', $type, $id),
                            source: new ErrorSource(pointer: $this->pointerRelationshipsIndex($meta->name, $idx).'/id'),
                            meta: ['type' => $type, 'id' => $id]
                        );
                    }
                }
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        return $resolved;
    }

    private function syncToOne(
        object $owner,
        ResourceMetadata $ownerMeta,
        RelationshipMetadata $relMeta,
        ?object $target
    ): void {
        $field = $relMeta->propertyPath ?? $relMeta->name;
        $cm = $this->em->getClassMetadata($ownerMeta->dataClass);
        if (!$cm->hasAssociation($field)) {
            // fall back to accessor set (non-association field?)
            $this->accessor->setValue($owner, $field, $target);
            return;
        }

        /** @var array{targetEntity: class-string, mappedBy?: string, inversedBy?: string, isOwningSide: bool, type: int} $assoc */
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
        if ($assoc['isOwningSide']) {
            $this->callSetter($owner, $field, $target);

            // keep inverse in sync when inversedBy defined
            if (!empty($assoc['inversedBy'])) {
                $inverseField = (string)$assoc['inversedBy'];
                if ($target !== null) {
                    $this->setInverseSide($target, $inverseField, $owner, $assoc['type']);
                }
            }
            return;
        }

        // Inverse side: set on target owning side if mappedBy available
        if (!empty($assoc['mappedBy']) && $target !== null) {
            $owningField = (string)$assoc['mappedBy'];
            $this->callSetter($target, $owningField, $owner);
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
        object $owner,
        ResourceMetadata $ownerMeta,
        RelationshipMetadata $relMeta,
        array $desired
    ): void {
        $field = $relMeta->propertyPath ?? $relMeta->name;

        // Ensure collection exists
        $collection = $this->getOrInitCollection($owner, $field);

        // Build current id set
        $uow = $this->em->getUnitOfWork();
        $currentById = [];
        foreach ($collection as $e) {
            $currentById[(string)$uow->getSingleIdentifierValue($e)] = $e;
        }

        // Build desired set using batch resolution (eliminates N+1 query problem)
        // This resolves all entities in a single query instead of N individual queries
        $desiredById = $this->resolveTargetsBatch($desired, $relMeta);

        // Determine adds/removes by semantics
        $adds = [];
        foreach ($desiredById as $id => $obj) {
            if (!isset($currentById[$id])) {
                $adds[$id] = $obj;
            }
        }
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
            $this->removeLink($owner, $ownerMeta, $field, $obj);
        }
        // Apply adds
        foreach ($adds as $id => $obj) {
            $this->addLink($owner, $ownerMeta, $field, $obj);
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

    private function addLink(object $owner, ResourceMetadata $ownerMeta, string $field, object $target): void
    {
        $cm = $this->em->getClassMetadata($ownerMeta->dataClass);
        if ($cm->hasAssociation($field)) {
            /** @var array{inversedBy?: string, mappedBy?: string, isOwningSide: bool, type: int} $assoc */
            $assoc = $cm->getAssociationMapping($field);

            // Try domain adder on owner
            $adder = $this->guessAdderName($field);
            if (method_exists($owner, $adder)) {
                $owner->{$adder}($target);
                return;
            }

            // Owning side: mutate owner's collection
            if ($assoc['isOwningSide']) {
                $col = $this->getOrInitCollection($owner, $field);
                if (!$col->contains($target)) {
                    $col->add($target);
                }

                // keep inverse in sync when inversedBy exists
                if (!empty($assoc['inversedBy'])) {
                    $inverseField = (string)$assoc['inversedBy'];
                    $this->addInverse($target, $inverseField, $owner, $assoc['type']);
                }
                return;
            }

            // Inverse side: update owning side on target
            if (!empty($assoc['mappedBy'])) {
                $owningField = (string)$assoc['mappedBy'];
                $this->addInverse($target, $owningField, $owner, $assoc['type']);
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

    private function removeLink(object $owner, ResourceMetadata $ownerMeta, string $field, object $target): void
    {
        $cm = $this->em->getClassMetadata($ownerMeta->dataClass);
        if ($cm->hasAssociation($field)) {
            /** @var array{inversedBy?: string, mappedBy?: string, isOwningSide: bool, type: int} $assoc */
            $assoc = $cm->getAssociationMapping($field);

            // Try domain remover on owner
            $remover = $this->guessRemoverName($field);
            if (method_exists($owner, $remover)) {
                $owner->{$remover}($target);
                return;
            }

            if ($assoc['isOwningSide']) {
                $this->getOrInitCollection($owner, $field)->removeElement($target);
                if (!empty($assoc['inversedBy'])) {
                    $this->removeInverse($target, (string)$assoc['inversedBy'], $owner, $assoc['type']);
                }
                return;
            }

            if (!empty($assoc['mappedBy'])) {
                $this->removeInverse($target, (string)$assoc['mappedBy'], $owner, $assoc['type']);
                $this->getOrInitCollection($owner, $field)->removeElement($target);
                return;
            }
        }

        // Fallback
        $this->getOrInitCollection($owner, $field)->removeElement($target);
    }

    private function setInverseSide(object $target, string $inverseField, object $owner, int $assocType): void
    {
        // Determine inverse side type
        // ManyToOne → OneToMany (inverse)
        // OneToMany → ManyToOne (inverse)
        // ManyToMany → ManyToMany (inverse)
        // OneToOne → OneToOne (inverse)
        $inverseIsToMany = match ($assocType) {
            ORMClassMetadata::MANY_TO_ONE => true,  // Inverse of ManyToOne is OneToMany
            ORMClassMetadata::ONE_TO_MANY => false, // Inverse of OneToMany is ManyToOne
            ORMClassMetadata::MANY_TO_MANY => true, // Inverse of ManyToMany is ManyToMany
            ORMClassMetadata::ONE_TO_ONE => false,  // Inverse of OneToOne is OneToOne
            default => false,
        };

        // For *ToOne inverse side, prefer setter; for *ToMany inverse side, prefer adder
        if ($inverseIsToMany) {
            $adder = $this->guessAdderName($inverseField);
            if (method_exists($target, $adder)) {
                $target->{$adder}($owner);
                return;
            }
            $col = $this->getOrInitCollection($target, $inverseField);
            if (!$col->contains($owner)) {
                $col->add($owner);
            }
        } else {
            $this->callSetter($target, $inverseField, $owner);
        }
    }

    private function addInverse(object $target, string $owningField, object $owner, int $assocType): void
    {
        if ($this->isToMany($assocType)) {
            $adder = $this->guessAdderName($owningField);
            if (method_exists($target, $adder)) {
                $target->{$adder}($owner);
                return;
            }
            $this->getOrInitCollection($target, $owningField)->add($owner);
        } else {
            $this->callSetter($target, $owningField, $owner);
        }
    }

    private function removeInverse(object $target, string $field, object $owner, int $assocType): void
    {
        if ($this->isToMany($assocType)) {
            $remover = $this->guessRemoverName($field);
            if (method_exists($target, $remover)) {
                $target->{$remover}($owner);
                return;
            }
            $this->getOrInitCollection($target, $field)->removeElement($owner);
        } else {
            // to-one inverse: set null if currently points to $owner
            $current = $this->accessor->getValue($target, $field);
            if ($current === $owner) {
                $this->callSetter($target, $field, null);
            }
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

    private function getOrInitCollection(object $obj, string $field): Collection
    {
        $val = $this->accessor->getValue($obj, $field);
        if ($val instanceof Collection) {
            return $val;
        }
        $col = new ArrayCollection();
        $this->accessor->setValue($obj, $field, $col);
        return $col;
    }

    private function isToMany(int $assocType): bool
    {
        return \in_array($assocType, [
            ORMClassMetadata::ONE_TO_MANY,
            ORMClassMetadata::MANY_TO_MANY,
        ], true);
    }

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
}
