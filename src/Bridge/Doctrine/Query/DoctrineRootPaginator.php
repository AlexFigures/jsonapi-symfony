<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Query;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\Tools\Pagination\Window;

/** @internal Pages root entities before any representation fetch joins. */
final class DoctrineRootPaginator
{
    /** @return array{list<object>, int} */
    public function paginate(QueryBuilder $roots, int $offset, int $size): array
    {
        $roots->setFirstResult($offset)->setMaxResults($size);
        if ($roots->getDQLPart('join') !== []) {
            // Joins can come from filters, sorting or custom conditions, not just includes.
            if (class_exists(OffsetPaginator::class)) {
                $page = (new OffsetPaginator(fetchJoinCollection: true, useOutputWalkers: true))
                    ->paginate($roots->getQuery(), new Window($offset, $size));
                $total = $page->getTotalCount();
                $items = $page->getItems();
            } else {
                // Older supported ORM 3.x releases do not provide OffsetPaginator.
                $paginator = new Paginator($roots->getQuery(), true);
                $paginator->setUseOutputWalkers(true);
                $total = count($paginator);
                $items = iterator_to_array($paginator, false);
            }
        } else {
            $count = clone $roots;
            $alias = $count->getRootAliases()[0];
            $count->select('COUNT(' . $alias . ')')->resetDQLPart('orderBy')->setFirstResult(0)->setMaxResults(null);
            $total = (int) $count->getQuery()->getSingleScalarResult();
            /** @var list<mixed> $items */
            $items = $roots->getQuery()->getResult();
        }
        $objects = [];
        foreach ($items as $item) {
            if (!is_object($item)) {
                throw new \LogicException('Root pagination requires an entity query.');
            }
            $objects[] = $item;
        }
        return [$objects, $total];
    }
}
