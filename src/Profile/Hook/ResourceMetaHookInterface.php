<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Hook;

use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;

/** Optional document hook capability for resource-level metadata. */
interface ResourceMetaHookInterface
{
    /** @param array<string, mixed> $meta */
    public function onResourceMeta(ProfileContext $context, ResourceMetadata $metadata, array &$meta, object $model): void;
}
