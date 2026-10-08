<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Hook;

use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;

/** @api Optional endpoint-aware relationship count requirements. */
interface ContextualFetchPlanHookInterface extends FetchPlanHookInterface
{
    /** @return list<string> */
    public function relationshipCountsForContext(ResourceMetadata $metadata, ProfileContext $context): array;
}
