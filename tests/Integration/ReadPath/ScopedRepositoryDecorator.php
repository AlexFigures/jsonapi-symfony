<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\ReadPath;

use AlexFigures\JsonApi\Bridge\Doctrine\Query\DoctrineCollectionQueryProviderInterface;
use AlexFigures\JsonApi\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use AlexFigures\JsonApi\Contract\Data\ResourceIdentifier;
use AlexFigures\JsonApi\Contract\Data\ResourceRepository;
use AlexFigures\JsonApi\Contract\Data\Slice;
use AlexFigures\JsonApi\Query\Criteria;
use Doctrine\ORM\QueryBuilder;

final class ScopedRepositoryDecorator implements ResourceRepository, DoctrineCollectionQueryProviderInterface
{
    /** @var list<string> */
    public array $plannedTypes = [];

    public function __construct(private readonly GenericDoctrineRepository $inner, private readonly bool $projectQueries = true)
    {
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        return $this->inner->findCollection($type, $this->scope($type, $criteria));
    }

    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        return $this->inner->findOne($type, $id, $this->scope($type, $criteria));
    }

    /** @param list<ResourceIdentifier> $identifiers */
    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        foreach ($identifiers as $identifier) {
            $model = $this->findOne($type, $identifier->id, new Criteria());
            if ($model !== null) {
                yield $model;
            }
        }
    }

    public function collectionQuery(string $type, Criteria $criteria): ?QueryBuilder
    {
        if (!$this->projectQueries) {
            return null; // Equivalent safe fallback when a decorator does not expose the capability.
        }
        $this->plannedTypes[] = $type;
        return $this->inner->collectionQuery($type, $this->scope($type, $criteria));
    }

    private function scope(string $type, Criteria $criteria): Criteria
    {
        $criteria = clone $criteria;
        if ($type === 'tags') {
            $criteria->customConditions[] = static fn (QueryBuilder $query) => $query->andWhere('e.id <> :hidden')->setParameter('hidden', 'tag-0001');
        }
        return $criteria;
    }
}
