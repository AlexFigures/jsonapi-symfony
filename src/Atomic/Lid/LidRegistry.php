<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Atomic\Lid;

use AlexFigures\Symfony\Http\Exception\BadRequestException;

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

        // Check if LID was already associated with a different ID
        $existingId = $this->entries[$lid]['id'];
        if ($existingId !== null && $existingId !== $id) {
            throw new BadRequestException(
                sprintf('Duplicate local identifier "%s". Each lid must be unique within an atomic operations request.', $lid)
            );
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
