<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Attribute;

use Attribute;
use JsonApi\Symfony\Resource\Metadata\RelationshipLinkingPolicy;

/**
 * Marks a property or method as a JSON:API relationship.
 *
 * Relationships represent connections between resources and are exposed in the
 * "relationships" member of the JSON:API resource document.
 *
 * Example to-one relationship:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * final class Article
 * {
 *     #[Relationship(toMany: false, targetType: 'authors')]
 *     public ?Author $author = null;
 * }
 * ```
 *
 * Example to-many relationship:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * final class Article
 * {
 *     #[Relationship(toMany: true, targetType: 'comments', inverse: 'article')]
 *     public array $comments = [];
 * }
 * ```
 *
 * Example relationship on method:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * final class Article
 * {
 *     #[Relationship(toMany: true, targetType: 'tags')]
 *     public function getTags(): array
 *     {
 *         return $this->tagCollection->toArray();
 *     }
 * }
 * ```
 *
 * Example with linking policy:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * final class Article
 * {
 *     #[Relationship(
 *         toMany: false,
 *         targetType: 'authors',
 *         linkingPolicy: RelationshipLinkingPolicy::VERIFY
 *     )]
 *     public ?Author $author = null;
 * }
 * ```
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Relationship
{
    /**
     * @param bool                                  $toMany        Whether this is a to-many relationship (true) or to-one (false)
     * @param string|null                           $inverse       Name of the inverse relationship on the target resource
     * @param string|null                           $targetType    JSON:API type of the target resource (e.g., 'authors', 'comments')
     * @param RelationshipLinkingPolicy|string|null $linkingPolicy How to resolve relationship references (REFERENCE or VERIFY)
     */
    public function __construct(
        public readonly bool $toMany = false,
        public readonly ?string $inverse = null,
        public readonly ?string $targetType = null,
        public readonly RelationshipLinkingPolicy|string|null $linkingPolicy = null,
    ) {
    }
}
