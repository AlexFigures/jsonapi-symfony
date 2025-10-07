<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\Locator;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Contract\Data\ResourcePersister;
use JsonApi\Symfony\Contract\Data\TypedResourcePersister;

/**
 * Locator для поиска подходящего Persister по типу ресурса.
 * 
 * Собирает все зарегистрированные персистеры через tagged_iterator
 * и выбирает подходящий на основе метода supports().
 */
final class ResourcePersisterLocator implements ResourcePersister
{
    /**
     * @param iterable<ResourcePersister> $persisters
     */
    public function __construct(
        private readonly iterable $persisters,
        private readonly ResourcePersister $fallbackPersister,
    ) {
    }

    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        return $this->getPersisterForType($type)->create($type, $changes, $clientId);
    }

    public function update(string $type, string $id, ChangeSet $changes): object
    {
        return $this->getPersisterForType($type)->update($type, $id, $changes);
    }

    public function delete(string $type, string $id): void
    {
        $this->getPersisterForType($type)->delete($type, $id);
    }

    private function getPersisterForType(string $type): ResourcePersister
    {
        foreach ($this->persisters as $persister) {
            if ($persister instanceof TypedResourcePersister && $persister->supports($type)) {
                return $persister;
            }
        }

        // Используем fallback (может быть NullObject или generic Doctrine persister)
        return $this->fallbackPersister;
    }
}

