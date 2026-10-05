<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Hook;

use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;

/** Optional declaration for document hooks that need relationship counts. */
interface FetchPlanHookInterface
{
    /** @return list<string> */
    public function relationshipCounts(ResourceMetadata $metadata): array;
}
