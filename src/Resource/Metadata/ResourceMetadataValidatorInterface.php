<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Metadata;

/** Optional persistence metadata validation at resource/route discovery. */
interface ResourceMetadataValidatorInterface
{
    public function validate(ResourceMetadata $resource): void;
}
