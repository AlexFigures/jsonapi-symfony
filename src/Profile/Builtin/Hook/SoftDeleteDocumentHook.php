<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Builtin\Hook;

use AlexFigures\Symfony\Profile\Hook\DocumentHook;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Document hook for soft delete profile.
 *
 * Adds soft delete metadata to resource documents.
 *
 * Usage:
 * - Adds deletedAt timestamp to resource meta if present
 * - Adds deletedBy user identifier to resource meta if present
 * - Adds isTrashed boolean flag to resource meta
 *
 */
final readonly class SoftDeleteDocumentHook implements DocumentHook, \AlexFigures\Symfony\Profile\Hook\ResourceMetaHookInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config = [])
    {
    }

    public function onResourceMeta(ProfileContext $context, ResourceMetadata $metadata, array &$meta, object $model): void
    {
        $attribute = $context->attributeReader()->getAttribute($metadata->dataClass, \AlexFigures\Symfony\Profile\Attribute\SoftDeletable::class);
        $field = $attribute->deletedByField ?? $this->config['deletedByField'] ?? null;
        if (!is_string($field)) {
            return;
        }
        $accessor = \Symfony\Component\PropertyAccess\PropertyAccess::createPropertyAccessor();
        if ($accessor->isReadable($model, $field)) {
            $actor = $accessor->getValue($model, $field);
            if ($actor === null || is_scalar($actor)) {
                $meta['deletedBy'] = $actor;
            } elseif ($actor instanceof \Stringable) {
                $meta['deletedBy'] = (string) $actor;
            }
        }
    }

    public function onTopLevelLinks(ProfileContext $context, array &$links, Request $request): void
    {
        // No top-level links modifications needed
    }

    public function onResourceRelationships(
        ProfileContext $context,
        ResourceMetadata $metadata,
        array &$relationshipsPayload,
        object $model
    ): void {
        // No relationship modifications needed
    }

    public function onTopLevelMeta(ProfileContext $context, array &$meta): void
    {
        // Could add global soft delete statistics here if needed
        // e.g., $meta['soft_delete_enabled'] = true;
    }
}
