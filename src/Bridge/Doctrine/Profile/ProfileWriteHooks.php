<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Profile;

use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/** @internal Profile changes are trusted server fields applied before entity validation. */
final readonly class ProfileWriteHooks
{
    public function __construct(private RequestStack $requests, private PropertyAccessorInterface $accessor, private ?\AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver $relationships = null)
    {
    }

    /** @return array<string, array{data: mixed}> Hook relationship changes applied to the model. */
    public function apply(object $entity, ResourceMetadata $metadata, bool $create, ?ChangeSet $input = null): array
    {
        $request = $this->requests->getCurrentRequest();
        $context = $request === null ? null : ProfileContext::fromRequest($request)?->forType($metadata->type);
        if ($context === null) {
            return [];
        }
        $changes = new ChangeSet($input->attributes ?? [], $input->relationships ?? []);
        foreach ($context->writeHooks() as $hook) {
            if ($create) {
                $hook->onBeforeCreate($context, $metadata->type, $changes);
            } else {
                $value = $this->accessor->getValue($entity, $metadata->idPropertyPath ?? 'id');
                $id = is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
                $hook->onBeforeUpdate($context, $metadata->type, $id, $changes);
            }
        }
        foreach ($changes->attributes as $field => $value) {
            if ($input !== null && array_key_exists($field, $input->attributes) && $input->attributes[$field] === $value) {
                continue;
            }
            $this->accessor->setValue($entity, $metadata->resolveFieldPath($field), $value);
        }
        $relationships = [];
        foreach ($changes->relationships as $name => $linkage) {
            if ($input === null || !array_key_exists($name, $input->relationships) || $input->relationships[$name] !== $linkage) {
                $relationships[$name] = $linkage;
            }
        }
        if ($relationships !== []) {
            $this->relationships?->applyRelationships($entity, $relationships, $metadata, $create);
        }
        return $relationships;
    }

    /** Returns true when the active profile replaces physical deletion. */
    public function softDelete(object $entity, ResourceMetadata $metadata): bool
    {
        $request = $this->requests->getCurrentRequest();
        $context = $request === null ? null : ProfileContext::fromRequest($request)?->forType($metadata->type);
        $profile = $context?->profile(\AlexFigures\JsonApi\Profile\Builtin\SoftDeleteProfile::URI);
        if (!$profile instanceof \AlexFigures\JsonApi\Profile\Builtin\SoftDeleteProfile) {
            return false;
        }
        $config = $profile->configuration();
        if (($config['delete_semantics'] ?? 'soft') !== 'soft') {
            return false;
        }
        $attribute = $context->attributeReader()->getAttribute($metadata->dataClass, \AlexFigures\JsonApi\Profile\Attribute\SoftDeletable::class);
        $field = $config['field'] ?? $config['deletedAtField'] ?? 'deletedAt';
        if ($attribute instanceof \AlexFigures\JsonApi\Profile\Attribute\SoftDeletable && ($attribute->deletedAtField !== 'deletedAt' || $field === 'deletedAt')) {
            $field = $attribute->deletedAtField;
        }
        $value = ($config['strategy'] ?? 'timestamp') === 'boolean' ? true : new \DateTimeImmutable();
        $this->accessor->setValue($entity, $field, $value);
        return true;
    }

    public function beforeDelete(string $type, string $id): void
    {
        $request = $this->requests->getCurrentRequest();
        $context = $request === null ? null : ProfileContext::fromRequest($request)?->forType($type);
        if ($context !== null) {
            foreach ($context->writeHooks() as $hook) {
                $hook->onBeforeDelete($context, $type, $id);
            }
        }
    }
}
