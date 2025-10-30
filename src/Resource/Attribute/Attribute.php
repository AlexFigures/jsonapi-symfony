<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Attribute;

use Attribute as PhpAttribute;

/**
 * Marks a property or method as a JSON:API resource attribute.
 *
 * Attributes represent the resource's data fields and are exposed in the
 * "attributes" member of the JSON:API resource document.
 *
 * Example usage on property:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * final class Article
 * {
 *     #[Attribute]
 *     public string $title;
 *
 *     #[Attribute]
 *     public \DateTimeImmutable $createdAt;
 * }
 * ```
 *
 * Example usage on method:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * final class Article
 * {
 *     #[Attribute(name: 'fullTitle')]
 *     public function getFullTitle(): string
 *     {
 *         return $this->title . ' - ' . $this->subtitle;
 *     }
 * }
 * ```
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
#[PhpAttribute(PhpAttribute::TARGET_PROPERTY | PhpAttribute::TARGET_METHOD)]
final class Attribute
{
    /**
     * @param string|null $name Attribute name in JSON:API document (defaults to property/method name)
     */
    public function __construct(
        public readonly ?string $name = null,
    ) {
    }
}
