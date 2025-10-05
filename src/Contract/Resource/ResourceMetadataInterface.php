<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Resource;

interface ResourceMetadataInterface
{
    public function getType(): string;
}
