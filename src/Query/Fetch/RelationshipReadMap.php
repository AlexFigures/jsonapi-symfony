<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Query\Fetch;

/** @internal Request-local results. Never installs partial ORM collections. */
final class RelationshipReadMap
{
    /** @var array<string, list<array{type: string, id: string}>> */
    private array $linkages = [];
    /** @var array<string, object> */
    private array $models = [];
    /** @var array<string, int> */
    private array $counts = [];
    /** @var array<string, object> Persistence owners for legacy computed getters, never representation replacements. */
    private array $sources = [];

    public function rememberSource(string $type, string $id, object $model): void
    {
        $this->sources[$this->key($type, $id)] = $model;
    }

    public function source(string $type, string $id): ?object
    {
        return $this->sources[$this->key($type, $id)] ?? null;
    }

    /** @param list<array{type: string, id: string}> $identifiers */
    public function put(string $type, string $id, string $relationship, array $identifiers): void
    {
        $this->linkages[$this->key($type, $id, $relationship)] = $identifiers;
    }

    /** @return list<array{type: string, id: string}>|null null means unsupported/unloaded, [] means loaded empty. */
    public function identifiers(string $type, string $id, string $relationship): ?array
    {
        return $this->linkages[$this->key($type, $id, $relationship)] ?? null;
    }

    public function remember(string $type, string $id, object $model): void
    {
        $this->models[$this->key($type, $id)] = $model;
    }

    public function model(string $type, string $id): ?object
    {
        return $this->models[$this->key($type, $id)] ?? null;
    }

    /** @return list<object>|null */
    public function related(string $type, string $id, string $relationship): ?array
    {
        $identifiers = $this->identifiers($type, $id, $relationship);
        if ($identifiers === null) {
            return null;
        }
        $models = [];
        foreach ($identifiers as $identifier) {
            $model = $this->model($identifier['type'], $identifier['id']);
            // Concurrent deletes can remove a target between identifier discovery and hydration.
            if ($model !== null) {
                $models[] = $model;
            }
        }
        return $models;
    }

    public function putCount(string $type, string $id, string $relationship, int $count): void
    {
        $this->counts[$this->key($type, $id, $relationship)] = $count;
    }

    public function count(string $type, string $id, string $relationship): ?int
    {
        return $this->counts[$this->key($type, $id, $relationship)] ?? null;
    }

    private function key(string $type, string $id, ?string $relationship = null): string
    {
        return json_encode([$type, $id, $relationship], \JSON_THROW_ON_ERROR);
    }
}
