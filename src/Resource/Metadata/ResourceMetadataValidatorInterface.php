<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Metadata;

/** Optional persistence metadata validation at resource/route discovery.
 * @internal
 */
interface ResourceMetadataValidatorInterface
{
    public function validate(ResourceMetadata $resource): void;
}
