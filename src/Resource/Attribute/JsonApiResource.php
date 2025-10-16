<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Attribute;

use AlexFigures\Symfony\Resource\Definition\ReadProjection;
use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;
use Attribute;

/**
 * Marks a PHP class as a JSON:API resource.
 *
 * This attribute declares that a class represents a JSON:API resource type
 * and configures its basic metadata and serialization contexts.
 *
 * Example usage:
 * ```php
 * use Symfony\Component\Serializer\Annotation\Groups;
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
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class JsonApiResource
{
    /**
     * @param string               $type                   JSON:API resource type (e.g., 'articles', 'authors')
     * @param array<string, mixed> $normalizationContext   Context for serialization (reading). Use ['groups' => ['resource:read']] to control which attributes are exposed.
     * @param array<string, mixed> $denormalizationContext Context for deserialization (writing). Use ['groups' => ['resource:write']] to control which attributes can be modified.
     * @param string|null          $routePrefix            Optional route prefix for this resource (defaults to global prefix)
     * @param string|null          $description            Optional human-readable description for documentation
     * @param bool                 $exposeId               Whether to expose the ID in the resource document (default: true)
     */
    /**
     * @param array<string, mixed>          $normalizationContext
     * @param array<string, mixed>          $denormalizationContext
     * @param array<string, string>         $fieldMap
     * @param array<string, RelationshipLinkingPolicy> $relationshipPolicies
     * @param array<string, class-string>   $writeRequests
     */
    public function __construct(
        public readonly string $type,
        public readonly array $normalizationContext = [],
        public readonly array $denormalizationContext = [],
        public readonly ?string $routePrefix = null,
        public readonly ?string $description = null,
        public readonly bool $exposeId = true,
        public readonly ?string $dataClass = null,
        public readonly ?string $viewClass = null,
        public readonly ReadProjection $readProjection = ReadProjection::ENTITY,
        public readonly array $fieldMap = [],
        public readonly array $relationshipPolicies = [],
        public readonly array $writeRequests = [],
        public readonly ?string $versionResolver = null,
    ) {
    }

    /**
     * Get normalization groups (for reading).
     *
     * @return list<string>
     */
    public function getNormalizationGroups(): array
    {
        return $this->normalizationContext['groups'] ?? [];
    }

    /**
     * Get denormalization groups (for writing).
     *
     * @return list<string>
     */
    public function getDenormalizationGroups(): array
    {
        return $this->denormalizationContext['groups'] ?? [];
    }
}
