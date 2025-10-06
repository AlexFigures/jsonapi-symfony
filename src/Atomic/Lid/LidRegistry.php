<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Atomic\Lid;

final class LidRegistry
{
    /**
     * @var array<string, array{type: string, id: string|null}>
     */
    private array $entries = [];

    public function register(string $lid, string $type): void
    {
        if (isset($this->entries[$lid])) {
            return;
        }

        $this->entries[$lid] = ['type' => $type, 'id' => null];
    }

    public function associate(string $lid, string $type, string $id): void
    {
        if (!isset($this->entries[$lid])) {
            $this->entries[$lid] = ['type' => $type, 'id' => $id];
            return;
        }

        $this->entries[$lid]['type'] = $type;
        $this->entries[$lid]['id'] = $id;
    }

    public function has(string $lid): bool
    {
        return isset($this->entries[$lid]);
    }

    public function getType(string $lid): ?string
    {
        return $this->entries[$lid]['type'] ?? null;
    }

    public function resolveId(string $lid): ?string
    {
        return $this->entries[$lid]['id'] ?? null;
    }
}
