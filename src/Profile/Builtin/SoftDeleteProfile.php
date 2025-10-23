<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Builtin;

use AlexFigures\Symfony\Profile\Attribute\SoftDeletable;
use AlexFigures\Symfony\Profile\Builtin\Hook\SoftDeleteDocumentHook;
use AlexFigures\Symfony\Profile\Builtin\Hook\SoftDeleteQueryHook;
use AlexFigures\Symfony\Profile\Builtin\Hook\SoftDeleteWriteHook;
use AlexFigures\Symfony\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\Symfony\Profile\ProfileInterface;
use AlexFigures\Symfony\Profile\Validation\FieldRequirement;
use AlexFigures\Symfony\Profile\Validation\ProfileRequirements;

/**
 * Soft Delete Profile.
 *
 * Provides soft delete semantics for resources:
 * - Filters out soft-deleted items by default
 * - Supports ?filter[withTrashed]=true to include deleted items
 * - Supports ?filter[onlyTrashed]=true to show only deleted items
 * - Intercepts delete operations (hook is informational)
 * - Adds soft delete metadata to documents
 *
 * @phpstan-type SoftDeleteConfig array{
 *     documentation?: string,
 *     deletedAtField?: string,
 *     deletedByField?: string,
 *     withTrashedParam?: string,
 *     onlyTrashedParam?: string,
 *     userProvider?: callable(): ?string
 * }
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
        yield new SoftDeleteQueryHook($this->config);
        yield new SoftDeleteWriteHook();
        yield new SoftDeleteDocumentHook();
    }

    public function requirements(): ProfileRequirements
    {
        return new ProfileRequirements(
            attribute: SoftDeletable::class,
            fields: [
                'deletedAt' => new FieldRequirement(
                    type: \DateTimeImmutable::class,
                    nullable: true,
                    optional: false,
                    description: 'Timestamp when entity was soft-deleted'
                ),
                'deletedBy' => new FieldRequirement(
                    type: 'string',
                    nullable: true,
                    optional: true,
                    description: 'User who deleted the entity (optional)'
                ),
            ],
            description: 'Enables soft-delete semantics for resources'
        );
    }
}
