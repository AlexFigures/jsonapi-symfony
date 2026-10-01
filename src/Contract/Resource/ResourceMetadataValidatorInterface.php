<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Resource;

use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;

/** Optional persistence metadata validation at resource/route discovery. */
interface ResourceMetadataValidatorInterface
{
    public function validate(ResourceMetadata $resource): void;
}
