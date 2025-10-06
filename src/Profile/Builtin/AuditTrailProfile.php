<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Profile\Builtin;

use JsonApi\Symfony\Profile\Descriptor\ProfileDescriptor;
use JsonApi\Symfony\Profile\ProfileInterface;

/**
 * @phpstan-type AuditTrailConfig array{documentation?: string}
 */
final class AuditTrailProfile implements ProfileInterface
{
    public const URI = 'urn:jsonapi:profile:audit-trail';

    /**
     * @param AuditTrailConfig $config
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
            'Audit Trail',
            '1.0',
            $this->config['documentation'] ?? null,
            'Tracks created/updated metadata for resources.',
            ['write', 'document-meta']
        );
    }

    public function hooks(): iterable
    {
        return [];
    }
}
