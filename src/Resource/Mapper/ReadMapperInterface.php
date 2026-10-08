<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Mapper;

use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Resource\Definition\ResourceDefinition;

/** @api */
interface ReadMapperInterface
{
    public function toView(mixed $row, ResourceDefinition $definition, Criteria $criteria): object;
}
