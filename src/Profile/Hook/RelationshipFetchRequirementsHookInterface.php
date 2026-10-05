<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Hook;

use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;

/** Declare document-hook reads before execution; consume ProfileContext::relationshipReads afterwards. */
interface RelationshipFetchRequirementsHookInterface
{
    /** @return array<string, 'identifiers'|'models'|'count'> */
    public function relationshipReads(ResourceMetadata $metadata): array;
}
