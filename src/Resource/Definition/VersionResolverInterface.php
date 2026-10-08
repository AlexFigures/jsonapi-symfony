<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Definition;

use AlexFigures\JsonApi\Profile\ProfileContext;

/** @api */
interface VersionResolverInterface
{
    public function resolve(ProfileContext $context): VersionDefinition;
}
