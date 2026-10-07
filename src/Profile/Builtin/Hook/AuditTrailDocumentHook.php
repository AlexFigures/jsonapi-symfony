<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Builtin\Hook;

use AlexFigures\Symfony\Profile\Hook\DocumentHook;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use Symfony\Component\HttpFoundation\Request;

/** Adds configured, readable audit fields without loading relationships. */
final readonly class AuditTrailDocumentHook implements DocumentHook, \AlexFigures\Symfony\Profile\Hook\ResourceMetaHookInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config = [])
    {
    }

    public function onResourceMeta(ProfileContext $context, ResourceMetadata $metadata, array &$meta, object $model): void
    {
        if (!($this->config['expose_in_meta'] ?? true)) {
            return;
        }
        $attribute = $context->attributeReader()->getAttribute($metadata->dataClass, \AlexFigures\Symfony\Profile\Attribute\Auditable::class);
        $fields = [
            'createdAt' => $attribute->createdAtField ?? $this->config['created_at'] ?? $this->config['createdAtField'] ?? 'createdAt',
            'updatedAt' => $attribute->updatedAtField ?? $this->config['updated_at'] ?? $this->config['updatedAtField'] ?? 'updatedAt',
            'createdBy' => $attribute->createdByField ?? $this->config['created_by'] ?? $this->config['createdByField'] ?? 'createdBy',
            'updatedBy' => $attribute->updatedByField ?? $this->config['updated_by'] ?? $this->config['updatedByField'] ?? 'updatedBy',
        ];
        $accessor = \Symfony\Component\PropertyAccess\PropertyAccess::createPropertyAccessor();
        $audit = [];
        foreach ($fields as $name => $field) {
            if (!is_string($field) || !$accessor->isReadable($model, $field)) {
                continue;
            }
            $value = $accessor->getValue($model, $field);
            if ($value instanceof \DateTimeInterface) {
                $audit[$name] = $value->format(\DateTimeInterface::ATOM);
            } elseif ($value === null || is_scalar($value)) {
                $audit[$name] = $value;
            }
        }
        if ($audit !== []) {
            $meta['audit'] = $audit;
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
        // Audit trail metadata goes in resource meta, not relationships
    }

    public function onTopLevelMeta(ProfileContext $context, array &$meta): void
    {
        // Could add global audit trail info here if needed
        // e.g., $meta['audit_trail_enabled'] = true;
    }
}
