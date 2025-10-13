<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Locator;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Contract\Data\ResourcePersister;
use AlexFigures\Symfony\Contract\Data\TypedResourcePersister;

/**
 * Locator for finding suitable Persister by resource type.
 *
 * Collects all registered persisters via tagged_iterator
 * and selects the appropriate one based on the supports() method.
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

        // Use fallback (can be NullObject or generic Doctrine persister)
        return $this->fallbackPersister;
    }
}
