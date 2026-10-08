<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Hook;

use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;

/** Optional declaration for document hooks that need relationship counts.
 * @api
 */
interface FetchPlanHookInterface
{
    /** @return list<string> */
    public function relationshipCounts(ResourceMetadata $metadata): array;
}
