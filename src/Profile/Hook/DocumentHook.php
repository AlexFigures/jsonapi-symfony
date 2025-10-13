<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Hook;

use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use Symfony\Component\HttpFoundation\Request;

interface DocumentHook
{
    /**
     * @param array<string, string|list<string>> $links
     */
    public function onTopLevelLinks(ProfileContext $context, array &$links, Request $request): void;

    /**
     * @param array<string, array<string, mixed>> $relationshipsPayload
     */
    public function onResourceRelationships(ProfileContext $context, ResourceMetadata $metadata, array &$relationshipsPayload, object $model): void;

    /**
     * @param array<string, mixed> $meta
     */
    public function onTopLevelMeta(ProfileContext $context, array &$meta): void;
}
