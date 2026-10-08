<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Hook;

use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;

/** Optional document hook capability for resource-level metadata.
 * @api
 */
interface ResourceMetaHookInterface
{
    /** @param array<string, mixed> $meta */
    public function onResourceMeta(ProfileContext $context, ResourceMetadata $metadata, array &$meta, object $model): void;
}
