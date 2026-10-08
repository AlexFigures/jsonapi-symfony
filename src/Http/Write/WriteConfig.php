<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Write;

/** @internal */
final class WriteConfig
{
    /**
     * @param array<string, bool> $clientIdAllowed
     */
    public function __construct(
        public bool $allowRelationshipWrites = false,
        public array $clientIdAllowed = [],
    ) {
    }

    public function allowClientId(string $type): bool
    {
        return $this->clientIdAllowed[$type] ?? false;
    }
}
