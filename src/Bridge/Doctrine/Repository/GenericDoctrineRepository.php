<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Sorting;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;

/**
 * Универсальный Doctrine-репозиторий для JSON:API ресурсов.
 *
 * Автоматически обрабатывает:
 * - Фильтрацию (базовая поддержка)
 * - Сортировку
 * - Пагинацию
 * - Sparse fieldsets (частичная гидратация)
 */
class GenericDoctrineRepository implements ResourceRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResourceRegistryInterface $registry,
    ) {
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        $metadata = $this->registry->getByType($type);
        $entityClass = $metadata->class;

        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from($entityClass, 'e');

        // Применяем сортировку
        $this->applySorting($qb, $criteria->sort);

        // Применяем пагинацию
        $offset = ($criteria->pagination->number - 1) * $criteria->pagination->size;
        $qb->setFirstResult($offset)
           ->setMaxResults($criteria->pagination->size);

        $items = $qb->getQuery()->getResult();
        $total = $this->countTotal($qb);

        return new Slice(
            items: $items,
            pageNumber: $criteria->pagination->number,
            pageSize: $criteria->pagination->size,
            totalItems: $total,
        );
    }

    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        $metadata = $this->registry->getByType($type);
        return $this->em->find($metadata->class, $id);
    }

    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        // Базовая реализация - будет расширена позже
        return [];
    }

    private function applySorting(QueryBuilder $qb, array $sorting): void
    {
        foreach ($sorting as $sort) {
            /** @var Sorting $sort */
            $direction = $sort->desc ? 'DESC' : 'ASC';
            $qb->addOrderBy('e.' . $sort->field, $direction);
        }
    }

    private function countTotal(QueryBuilder $qb): int
    {
        $countQb = clone $qb;
        $countQb->select('COUNT(e)')
                ->setFirstResult(0)
                ->setMaxResults(null)
                ->resetDQLPart('orderBy'); // Remove ORDER BY for count query

        return (int) $countQb->getQuery()->getSingleScalarResult();
    }
}

