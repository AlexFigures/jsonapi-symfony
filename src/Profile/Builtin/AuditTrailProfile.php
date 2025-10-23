<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Builtin;

use AlexFigures\Symfony\Profile\Attribute\Auditable;
use AlexFigures\Symfony\Profile\Builtin\Hook\AuditTrailDocumentHook;
use AlexFigures\Symfony\Profile\Builtin\Hook\AuditTrailWriteHook;
use AlexFigures\Symfony\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\Symfony\Profile\ProfileInterface;
use AlexFigures\Symfony\Profile\Validation\FieldRequirement;
use AlexFigures\Symfony\Profile\Validation\ProfileRequirements;

/**
 * Audit Trail Profile.
 *
 * Automatically tracks creation and update metadata for resources:
 * - Sets createdAt and createdBy on resource creation
 * - Sets updatedAt and updatedBy on resource update
 * - Adds audit metadata to resource documents
 *
 * Requires entities to have the configured fields (default: createdAt, createdBy, updatedAt, updatedBy).
 *
 * @phpstan-type AuditTrailConfig array{
 *     documentation?: string,
 *     createdAtField?: string,
 *     createdByField?: string,
 *     updatedAtField?: string,
 *     updatedByField?: string,
 *     userProvider?: callable(): ?string
 * }
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
        yield new AuditTrailWriteHook($this->config);
        yield new AuditTrailDocumentHook();
    }

    public function requirements(): ProfileRequirements
    {
        return new ProfileRequirements(
            attribute: Auditable::class,
            fields: [
                'createdAt' => new FieldRequirement(
                    type: \DateTimeImmutable::class,
                    nullable: false,
                    optional: false,
                    description: 'Timestamp when entity was created'
                ),
                'updatedAt' => new FieldRequirement(
                    type: \DateTimeImmutable::class,
                    nullable: false,
                    optional: false,
                    description: 'Timestamp when entity was last updated'
                ),
                'createdBy' => new FieldRequirement(
                    type: 'string',
                    nullable: true,
                    optional: true,
                    description: 'User who created the entity (optional)'
                ),
                'updatedBy' => new FieldRequirement(
                    type: 'string',
                    nullable: true,
                    optional: true,
                    description: 'User who last updated the entity (optional)'
                ),
            ],
            description: 'Tracks creation and update metadata for resources'
        );
    }
}
