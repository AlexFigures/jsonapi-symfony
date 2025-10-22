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
 * Note: Currently this hook is a placeholder. Soft delete metadata
 * should be exposed through resource attributes or meta in the serialization layer.
 * This hook can be extended to add top-level meta information about soft delete.
 */
final readonly class SoftDeleteDocumentHook implements DocumentHook
{
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
