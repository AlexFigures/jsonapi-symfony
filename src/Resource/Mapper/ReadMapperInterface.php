<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Mapper;

use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Resource\Definition\ResourceDefinition;

interface ReadMapperInterface
{
    public function toView(mixed $row, ResourceDefinition $definition, Criteria $criteria): object;
}
