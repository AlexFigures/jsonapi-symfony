<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Profile\Builtin;

use JsonApi\Symfony\Profile\Descriptor\ProfileDescriptor;
use JsonApi\Symfony\Profile\ProfileInterface;

/**
 * @phpstan-type SoftDeleteConfig array{documentation?: string}
 */
final class SoftDeleteProfile implements ProfileInterface
{
    public const URI = 'urn:jsonapi:profile:soft-delete';

    /**
     * @param SoftDeleteConfig $config
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
            'Soft Delete',
            '1.0',
            $this->config['documentation'] ?? null,
            'Adds soft delete semantics and filtering helpers.',
            ['query', 'write', 'document-meta']
        );
    }

    public function hooks(): iterable
    {
        return [];
    }
}
