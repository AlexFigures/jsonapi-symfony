<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Definition;

use AlexFigures\JsonApi\Resource\Metadata\RelationshipLinkingPolicy;

/**
 * Represents a resource version resolved for a specific profile/context.
 * @api
 */
final readonly class VersionDefinition
{
    /**
     * @param array<string, class-string>              $writeRequests
     * @param array<string, string>                    $fieldMap
     * @param array<string, RelationshipLinkingPolicy> $relationshipPolicies
     */
    public function __construct(
        public ?string $viewClass,
        public array $writeRequests,
        public ReadProjection $readProjection,
        public array $fieldMap,
        public array $relationshipPolicies,
    ) {
    }
}
