<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Hook;

use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;

/** Declare document-hook reads before execution; consume ProfileContext::relationshipReads afterwards.
 * @api
 */
interface RelationshipFetchRequirementsHookInterface
{
    /** @return array<string, 'identifiers'|'models'|'count'> */
    public function relationshipReads(ResourceMetadata $metadata): array;
}
