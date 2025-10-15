<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Mapper;

use AlexFigures\Symfony\Resource\Definition\ResourceDefinition;
use AlexFigures\Symfony\Resource\Write\WriteContext;

interface WriteMapperInterface
{
    public function instantiate(ResourceDefinition $definition, object $requestDto, WriteContext $context): object;

    public function apply(object $entity, object $requestDto, ResourceDefinition $definition, WriteContext $context): void;
}
