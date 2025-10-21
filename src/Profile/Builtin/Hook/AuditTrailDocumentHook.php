<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Builtin\Hook;

use AlexFigures\Symfony\Profile\Hook\DocumentHook;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Document hook for audit trail profile.
 *
 * Adds audit trail metadata to resource documents.
 *
 * Usage:
 * - Adds createdAt, createdBy, updatedAt, updatedBy to resource meta
 * - Only includes fields that exist on the entity
 * - Formats timestamps as ISO 8601
 *
 * Note: Currently this hook is a placeholder. Audit trail metadata
 * should be exposed through resource attributes or meta in the serialization layer.
 * This hook can be extended to add top-level meta information about audit trail.
 */
final readonly class AuditTrailDocumentHook implements DocumentHook
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
        // Audit trail metadata goes in resource meta, not relationships
    }

    public function onTopLevelMeta(ProfileContext $context, array &$meta): void
    {
        // Could add global audit trail info here if needed
        // e.g., $meta['audit_trail_enabled'] = true;
    }
}

