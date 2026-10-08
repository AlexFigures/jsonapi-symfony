<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Builtin;

use AlexFigures\JsonApi\Profile\Builtin\Hook\RelationshipCountsDocumentHook;
use AlexFigures\JsonApi\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\JsonApi\Profile\ProfileInterface;
use AlexFigures\JsonApi\Profile\Validation\ProfileRequirements;

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
 *     relationship_meta_key?: string, compute_in_related_endpoints?: bool,
 *     includeRelationships?: list<string>,
 *     excludeRelationships?: list<string>
 * }
 * @api
 */
final readonly class RelationshipCountsProfile implements ProfileInterface
{
    public const URI = 'urn:jsonapi:profile:rel-counts';

    /**
     * @param RelationshipCountsConfig $config
     */
    public function __construct(private array $config = [])
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

    public function requirements(): ?ProfileRequirements
    {
        // This profile has no requirements - it only adds metadata to documents
        // and doesn't require any specific fields or attributes on entities
        return null;
    }
}
