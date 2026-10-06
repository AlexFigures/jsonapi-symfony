<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Locator;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Contract\Data\ResourceProcessor;
use AlexFigures\Symfony\Contract\Data\TypedResourcePersister;

/** @internal Adapts the documented legacy typed persister contract to write controllers. */
final readonly class ResourceProcessorLocator implements ResourceProcessor
{
    /** @param iterable<\AlexFigures\Symfony\Contract\Data\ResourcePersister> $persisters */
    public function __construct(private iterable $persisters, private ResourceProcessor $fallback)
    {
    }

    public function processCreate(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        $persister = $this->persister($type);
        return $persister === null ? $this->fallback->processCreate($type, $changes, $clientId) : $persister->create($type, $changes, $clientId);
    }

    public function processUpdate(string $type, string $id, ChangeSet $changes): object
    {
        $persister = $this->persister($type);
        return $persister === null ? $this->fallback->processUpdate($type, $id, $changes) : $persister->update($type, $id, $changes);
    }

    public function processDelete(string $type, string $id): void
    {
        $persister = $this->persister($type);
        if ($persister === null) {
            $this->fallback->processDelete($type, $id);
        } else {
            $persister->delete($type, $id);
        }
    }

    private function persister(string $type): ?TypedResourcePersister
    {
        foreach ($this->persisters as $persister) {
            if ($persister instanceof TypedResourcePersister && $persister->supports($type)) {
                return $persister;
            }
        }
        return null;
    }
}
