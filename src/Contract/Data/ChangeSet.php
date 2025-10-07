<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

/**
 * Represents attribute changes for create/update operations.
 *
 * This DTO is passed to ResourcePersister::create() and ResourcePersister::update()
 * containing the attributes to be written to the resource.
 *
 * Example usage:
 * ```php
 * $changeSet = new ChangeSet([
 *     'title' => 'New Article Title',
 *     'body' => 'Article content...',
 *     'publishedAt' => new \DateTimeImmutable(),
 * ]);
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
     * @param array<string, mixed> $attributes Attribute name => value map
     */
    public function __construct(public array $attributes = [])
    {
    }
}
