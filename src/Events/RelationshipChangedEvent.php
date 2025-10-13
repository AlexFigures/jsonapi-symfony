<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Events;

final class RelationshipChangedEvent
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $relationship,
        public readonly string $operation,
    ) {
    }
}
