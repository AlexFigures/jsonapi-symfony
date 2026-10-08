<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Symfony\Locator;

use AlexFigures\JsonApi\Contract\Data\ResourceRepository;
use AlexFigures\JsonApi\Contract\Data\Slice;
use AlexFigures\JsonApi\Contract\Data\TypedResourceRepository;
use AlexFigures\JsonApi\Query\Criteria;

/**
 * @api Type selection is supported for repository decorators using optional capabilities.
 *
 * Locator for finding suitable Repository by resource type.
 *
 * Collects all registered repositories via tagged_iterator
 * and selects the appropriate one based on the supports() method.
 */
final readonly class ResourceRepositoryLocator implements ResourceRepository
{
    /**
     * @param iterable<ResourceRepository> $repositories
     */
    public function __construct(
        private iterable $repositories,
        private ResourceRepository $fallbackRepository,
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

    /** @api Select the authoritative provider before using optional capabilities. */
    public function getRepositoryForType(string $type): ResourceRepository
    {
        foreach ($this->repositories as $repository) {
            if ($repository instanceof TypedResourceRepository && $repository->supports($type)) {
                return $repository;
            }
        }

        // Use fallback (can be NullObject or generic Doctrine repository)
        return $this->fallbackRepository;
    }
}
