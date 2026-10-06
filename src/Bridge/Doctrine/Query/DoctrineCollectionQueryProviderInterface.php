<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\Query;

use AlexFigures\Symfony\Query\Criteria;
use Doctrine\ORM\QueryBuilder;

/** @internal Builds the complete collection visibility predicate without executing SQL. Return null when visibility cannot safely be projected. */
interface DoctrineCollectionQueryProviderInterface
{
    public function collectionQuery(string $type, Criteria $criteria): ?QueryBuilder;
}
