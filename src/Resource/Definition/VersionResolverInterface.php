<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Definition;

use AlexFigures\Symfony\Profile\ProfileContext;

interface VersionResolverInterface
{
    public function resolve(ProfileContext $context): VersionDefinition;
}
