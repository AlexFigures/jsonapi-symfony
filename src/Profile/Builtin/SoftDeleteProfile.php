<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Builtin;

use AlexFigures\JsonApi\Profile\Attribute\SoftDeletable;
use AlexFigures\JsonApi\Profile\Builtin\Hook\SoftDeleteDocumentHook;
use AlexFigures\JsonApi\Profile\Builtin\Hook\SoftDeleteQueryHook;
use AlexFigures\JsonApi\Profile\Builtin\Hook\SoftDeleteWriteHook;
use AlexFigures\JsonApi\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\JsonApi\Profile\ProfileInterface;
use AlexFigures\JsonApi\Profile\Validation\FieldRequirement;
use AlexFigures\JsonApi\Profile\Validation\ProfileRequirements;

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
 *     field?: string, strategy?: string, default_visibility?: string, delete_semantics?: string, query_flags?: array{with_deleted?: string, only_deleted?: string},
 *     documentation?: string,
 *     deletedAtField?: string,
 *     deletedByField?: string,
 *     withTrashedParam?: string,
 *     onlyTrashedParam?: string,
 *     userProvider?: callable(): ?string
 * }
 * @api
 */
final readonly class SoftDeleteProfile implements ProfileInterface
{
    public const URI = 'urn:jsonapi:profile:soft-delete';

    /**
     * @param SoftDeleteConfig $config
     */
    public function __construct(private array $config = [])
    {
    }

    /** @return SoftDeleteConfig */
    public function configuration(): array
    {
        return $this->config;
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
        yield new SoftDeleteDocumentHook($this->config);
    }

    public function requirements(): ProfileRequirements
    {
        return new ProfileRequirements(
            attribute: SoftDeletable::class,
            fields: [
                ($this->config['field'] ?? $this->config['deletedAtField'] ?? 'deletedAt') => new FieldRequirement(
                    type: ($this->config['strategy'] ?? 'timestamp') === 'boolean' ? 'bool' : \DateTimeImmutable::class,
                    nullable: ($this->config['strategy'] ?? 'timestamp') !== 'boolean',
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
