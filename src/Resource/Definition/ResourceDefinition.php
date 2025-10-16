<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Definition;

use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;

/**
 * Immutable DTO describing a JSON:API resource definition.
 */
final class ResourceDefinition
{
    /**
     * @param array<string, string> $fieldMap
     * @param array<string, RelationshipLinkingPolicy> $relationshipPolicies
     * @param array<string, class-string> $writeRequests
     */
    public function __construct(
        public readonly string $type,
        public readonly string $dataClass,
        public readonly ?string $viewClass,
        public readonly ReadProjection $readProjection,
        public readonly array $fieldMap,
        public readonly array $relationshipPolicies,
        public readonly array $writeRequests,
        public readonly ?VersionResolverInterface $versionResolver = null,
    ) {
    }

    public function getEffectiveViewClass(): string
    {
        return $this->viewClass ?? $this->dataClass;
    }
}
