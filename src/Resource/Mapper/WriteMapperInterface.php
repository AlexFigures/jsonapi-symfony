<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Mapper;

use AlexFigures\JsonApi\Resource\Definition\ResourceDefinition;
use AlexFigures\JsonApi\Resource\Write\WriteContext;

/** @api */
interface WriteMapperInterface
{
    public function instantiate(ResourceDefinition $definition, object $requestDto, WriteContext $context): object;

    public function apply(object $entity, object $requestDto, ResourceDefinition $definition, WriteContext $context): void;
}
