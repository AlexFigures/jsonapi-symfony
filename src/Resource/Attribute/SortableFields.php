<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Resource\Attribute;

use Attribute;

/**
 * Defines which fields are allowed for sorting in JSON:API requests.
 *
 * This attribute specifies a whitelist of fields that can be used in the `sort`
 * query parameter. Only fields listed here will be accepted; attempts to sort
 * by other fields will result in a 400 Bad Request error.
 *
 * Example usage:
 * ```php
 * #[JsonApiResource(type: 'articles')]
 * #[SortableFields(['title', 'createdAt', 'updatedAt', 'viewCount'])]
 * final class Article
 * {
 *     #[Id]
 *     #[Attribute]
 *     public string $id;
 *
 *     #[Attribute]
 *     public string $title;
 *
 *     #[Attribute(writable: false)]
 *     public \DateTimeImmutable $createdAt;
 *
 *     #[Attribute(writable: false)]
 *     public \DateTimeImmutable $updatedAt;
 *
 *     #[Attribute]
 *     public int $viewCount;
 * }
 * ```
 *
 * **Security Note**: Always use a whitelist approach for sorting to prevent:
 * - Information disclosure through timing attacks
 * - Performance issues from sorting on unindexed columns
 * - Exposure of internal field names
 *
 * **Request Example**:
 * ```
 * GET /api/articles?sort=-createdAt,title
 * ```
 * This sorts by `createdAt` descending, then by `title` ascending.
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class SortableFields
{
    /**
     * @var list<string>
     */
    public readonly array $fields;

    /**
     * @param list<string> $fields List of field names that can be used for sorting
     */
    public function __construct(array $fields)
    {
        $this->fields = array_values($fields);
    }
}

