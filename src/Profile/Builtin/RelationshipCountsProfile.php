<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Profile\Builtin;

use JsonApi\Symfony\Profile\Descriptor\ProfileDescriptor;
use JsonApi\Symfony\Profile\ProfileInterface;

final class RelationshipCountsProfile implements ProfileInterface
{
    public const URI = 'urn:jsonapi:profile:rel-counts';

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
        return [];
    }
}
