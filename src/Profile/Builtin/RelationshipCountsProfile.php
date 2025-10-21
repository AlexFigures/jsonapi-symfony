<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Builtin;

use AlexFigures\Symfony\Profile\Builtin\Hook\RelationshipCountsDocumentHook;
use AlexFigures\Symfony\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\Symfony\Profile\ProfileInterface;

/**
 * Relationship Counts Profile.
 *
 * Adds count metadata to to-many relationships in JSON:API documents.
 *
 * Example output:
 * {
 *   "data": {
 *     "type": "articles",
 *     "id": "1",
 *     "relationships": {
 *       "comments": {
 *         "data": [...],
 *         "meta": {"count": 42}
 *       }
 *     }
 *   }
 * }
 *
 * Works efficiently with Doctrine collections (counts without loading all items).
 *
 * @phpstan-type RelationshipCountsConfig array{
 *     documentation?: string,
 *     includeRelationships?: list<string>,
 *     excludeRelationships?: list<string>
 * }
 */
final class RelationshipCountsProfile implements ProfileInterface
{
    public const URI = 'urn:jsonapi:profile:rel-counts';

    /**
     * @param RelationshipCountsConfig $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    public function uri(): string
    {
        return self::URI;
    }

    public function descriptor(): ProfileDescriptor
    {
        return new ProfileDescriptor(
            self::URI,
            'Relationship Counts',
            '1.0',
            $this->config['documentation'] ?? null,
            'Augments relationship objects with meta counts.',
            ['document-relationships']
        );
    }

    public function hooks(): iterable
    {
        yield new RelationshipCountsDocumentHook($this->config);
    }
}
