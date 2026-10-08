<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Query;

use AlexFigures\JsonApi\Resource\Definition\ResourceDefinition;
use Doctrine\ORM\QueryBuilder;

/** @internal The same scalar DTO projection for root pages and included targets. */
final class DoctrineReadProjection
{
    public static function apply(QueryBuilder $query, ResourceDefinition $definition): void
    {
        $selects = [];
        foreach ($definition->fieldMap as $field => $expression) {
            $selects[] = $expression . ' AS ' . $field;
        }
        if ($selects === []) {
            $alias = $query->getRootAliases()[0];
            foreach ($query->getEntityManager()->getClassMetadata($definition->dataClass)->getFieldNames() as $field) {
                $selects[] = $alias . '.' . $field . ' AS ' . $field;
            }
        }
        $query->select(...$selects);
    }
}
