<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Definition;

use AlexFigures\JsonApi\Resource\Metadata\RelationshipLinkingPolicy;

/**
 * Immutable DTO describing a JSON:API resource definition.
 * @api
 */
final readonly class ResourceDefinition
{
    /**
     * @param array<string, string>                    $fieldMap
     * @param array<string, RelationshipLinkingPolicy> $relationshipPolicies
     * @param array<string, class-string>              $writeRequests
     * @param list<ResourceOperation>                  $allowedOperations
     */
    public function __construct(
        public string $type,
        public string $dataClass,
        public ?string $viewClass,
        public ReadProjection $readProjection,
        public array $fieldMap,
        public array $relationshipPolicies,
        public array $writeRequests,
        public ?VersionResolverInterface $versionResolver = null,
        public array $allowedOperations = [],
    ) {
    }

    public function getEffectiveViewClass(): string
    {
        return $this->viewClass ?? $this->dataClass;
    }
}
