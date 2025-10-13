<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Attribute;

use Attribute;

/**
 * Marks a property or method as the JSON:API resource identifier.
 *
 * Every JSON:API resource must have exactly one identifier field.
 * The identifier is exposed in the resource document as the "id" member.
 *
 * Example usage on property:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * final class Article
 * {
 *     #[Id]
 *     public string $id;
 * }
 * ```
 *
 * Example usage on method:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * final class Article
 * {
 *     #[Id]
 *     public function getId(): string
 *     {
 *         return $this->uuid->toString();
 *     }
 * }
 * ```
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Id
{
}
