<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\Locator;

use JsonApi\Symfony\Contract\Data\Criteria;
use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Contract\Data\TypedResourceRepository;
use JsonApi\Symfony\Http\Exception\NotFoundException;

/**
 * Locator для поиска подходящего Repository по типу ресурса.
 * 
 * Собирает все зарегистрированные репозитории через tagged_iterator
 * и выбирает подходящий на основе метода supports().
 */
final class ResourceRepositoryLocator implements ResourceRepository
{
    /**
     * @param iterable<ResourceRepository> $repositories
     */
    public function __construct(
        private readonly iterable $repositories,
        private readonly ResourceRepository $fallbackRepository,
    ) {
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        return $this->getRepositoryForType($type)->findCollection($type, $criteria);
    }

    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        return $this->getRepositoryForType($type)->findOne($type, $id, $criteria);
    }

    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        return $this->getRepositoryForType($type)->findRelated($type, $relationship, $identifiers);
    }

    private function getRepositoryForType(string $type): ResourceRepository
    {
        foreach ($this->repositories as $repository) {
            if ($repository instanceof TypedResourceRepository && $repository->supports($type)) {
                return $repository;
            }
        }

        // Используем fallback (может быть NullObject или generic Doctrine repository)
        return $this->fallbackRepository;
    }
}

