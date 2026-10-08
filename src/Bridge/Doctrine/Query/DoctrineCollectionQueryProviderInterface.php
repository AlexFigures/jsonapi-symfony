<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Query;

use AlexFigures\JsonApi\Query\Criteria;
use Doctrine\ORM\QueryBuilder;

/**
 * @api Builds complete collection visibility without SQL. Return null when it cannot safely be projected.
 *
 * Decorators must opt in explicitly and preserve all checks, filters and target scope in this path.
 * Forwarding an inner query while omitting outer policies is unsafe. Opaque decorators retain scoped fallback.
 */
interface DoctrineCollectionQueryProviderInterface
{
    public function collectionQuery(string $type, Criteria $criteria): ?QueryBuilder;
}
