<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Hook;

use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;

/** @api Optional endpoint-aware relationship count requirements. */
interface ContextualFetchPlanHookInterface extends FetchPlanHookInterface
{
    /** @return list<string> */
    public function relationshipCountsForContext(ResourceMetadata $metadata, ProfileContext $context): array;
}
