<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Profile\Hook;

use JsonApi\Symfony\Profile\ProfileContext;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use Symfony\Component\HttpFoundation\Request;

interface DocumentHook
{
    public function onTopLevelLinks(ProfileContext $context, array &$links, Request $request): void;

    public function onResourceRelationships(ProfileContext $context, ResourceMetadata $metadata, array &$relationshipsPayload, object $model): void;

    public function onTopLevelMeta(ProfileContext $context, array &$meta): void;
}
