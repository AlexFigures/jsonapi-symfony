<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Metadata;

/**
 * Defines how relationship references are resolved.
 */
enum RelationshipLinkingPolicy: string
{
    /**
     * Use lazy references (getReference) - faster but no existence validation.
     * FK errors will only appear on flush.
     */
    case REFERENCE = 'reference';

    /**
     * Verify entity existence (find) - slower but provides early validation.
     * Returns clear validation errors if entity doesn't exist.
     */
    case VERIFY = 'verify';
}
