<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Definition;

use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;

/**
 * Represents a resource version resolved for a specific profile/context.
 */
final class VersionDefinition
{
    /**
     * @param array<string, class-string> $writeRequests
     * @param array<string, string> $fieldMap
     * @param array<string, RelationshipLinkingPolicy> $relationshipPolicies
     */
    public function __construct(
        public readonly ?string $viewClass,
        public readonly array $writeRequests,
        public readonly ReadProjection $readProjection,
        public readonly array $fieldMap,
        public readonly array $relationshipPolicies,
    ) {
    }
}
