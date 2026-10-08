<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Attribute;

use AlexFigures\JsonApi\Resource\Definition\ReadProjection;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use AlexFigures\JsonApi\Resource\Metadata\RelationshipLinkingPolicy;
use Attribute;

/**
 * Marks a PHP class as a JSON:API resource.
 *
 * This attribute declares that a class represents a JSON:API resource type
 * and configures its basic metadata and serialization contexts.
 *
 * Example usage:
 * ```php
 * use Symfony\Component\Serializer\Attribute\Groups;
 * use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
 *
 * #[JsonApiResource(
 *     type: 'articles',
 *     normalizationContext: ['groups' => ['article:read']],
 *     denormalizationContext: ['groups' => ['article:write']],
 *     routePrefix: '/api',
 *     description: 'Blog articles',
 *     exposeId: true
 * )]
 * final class Article
 * {
 *     #[Id]
 *     #[Groups(['article:read'])]
 *     public string $id;
 *
 *     #[Attribute]
 *     #[Groups(['article:read', 'article:write'])]
 *     public string $title;
 *
 *     #[Attribute]
 *     #[Groups(['article:read'])]
 *     public \DateTimeImmutable $createdAt;
 * }
 * ```
 *
 * Example with limited operations (read-only resource):
 * ```php
 * #[JsonApiResource(
 *     type: 'audit-logs',
 *     operations: [ResourceOperation::INDEX, ResourceOperation::SHOW]
 * )]
 * final class AuditLog
 * {
 *     // Only GET collection and GET item routes will be registered
 *     // POST, PATCH, DELETE will return 404
 * }
 * ```
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class JsonApiResource
{
    /**
     * @param string                                   $type                   JSON:API resource type (e.g., 'articles', 'authors')
     * @param array<string, mixed>                     $normalizationContext   Context for serialization (reading). Use ['groups' => ['resource:read']] to control which attributes are exposed.
     * @param array<string, mixed>                     $denormalizationContext Context for deserialization (writing). Use ['groups' => ['resource:write']] to control which attributes can be modified.
     * @param string|null                              $routePrefix            Optional route prefix for this resource (defaults to global prefix)
     * @param string|null                              $description            Optional human-readable description for documentation
     * @param bool                                     $exposeId               Whether to accept the synthetic id in sparse fieldsets; protocol identity is always present (default: true)
     * @param list<ResourceOperation>|null             $operations             Allowed operations for this resource. Null means all operations are allowed (default).
     * @param array<string, string>                    $fieldMap
     * @param array<string, RelationshipLinkingPolicy> $relationshipPolicies
     * @param array<string, class-string>              $writeRequests
     */
    public function __construct(
        public string $type,
        public array $normalizationContext = [],
        public array $denormalizationContext = [],
        public ?string $routePrefix = null,
        public ?string $description = null,
        public bool $exposeId = true,
        public ?array $operations = null,
        public ?string $dataClass = null,
        public ?string $viewClass = null,
        public ReadProjection $readProjection = ReadProjection::ENTITY,
        public array $fieldMap = [],
        public array $relationshipPolicies = [],
        public array $writeRequests = [],
        public ?string $versionResolver = null,
    ) {
    }

    /**
     * Get normalization groups (for reading).
     *
     * @return list<string>
     */
    public function getNormalizationGroups(): array
    {
        return $this->normalizeGroups($this->normalizationContext);
    }

    /**
     * Get denormalization groups (for writing).
     *
     * @return list<string>
     */
    public function getDenormalizationGroups(): array
    {
        return $this->normalizeGroups($this->denormalizationContext);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<string>
     */
    private function normalizeGroups(array $context): array
    {
        $groups = $context['groups'] ?? [];

        if (!is_array($groups)) {
            return [];
        }

        $normalized = [];
        foreach ($groups as $group) {
            if (!is_string($group) || $group === '') {
                continue;
            }

            $normalized[] = $group;
        }

        return array_values(array_unique($normalized));
    }
}
