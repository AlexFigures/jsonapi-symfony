<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

/**
 * Represents attribute and relationship changes for create/update operations.
 *
 * This DTO is passed to ResourcePersister::create() and ResourcePersister::update()
 * containing the attributes and relationships to be written to the resource.
 *
 * Example usage:
 * ```php
 * $changeSet = new ChangeSet(
 *     attributes: [
 *         'title' => 'New Article Title',
 *         'body' => 'Article content...',
 *         'publishedAt' => new \DateTimeImmutable(),
 *     ],
 *     relationships: [
 *         'author' => ['data' => ['type' => 'authors', 'id' => '123']],
 *         'tags' => ['data' => [
 *             ['type' => 'tags', 'id' => '1'],
 *             ['type' => 'tags', 'id' => '2'],
 *         ]],
 *     ]
 * );
 *
 * $article = $persister->create('articles', $changeSet);
 * ```
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
final class ChangeSet
{
    /**
     * @param array<string, mixed>              $attributes    Attribute name => value map
     * @param array<string, array{data: mixed}> $relationships Relationship name => JSON:API relationship data
     */
    public function __construct(
        public array $attributes = [],
        public array $relationships = []
    ) {
    }
}
