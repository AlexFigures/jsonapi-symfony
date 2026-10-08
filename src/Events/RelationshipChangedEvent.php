<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Events;

/** @api */
final readonly class RelationshipChangedEvent
{
    public function __construct(
        public string $type,
        public string $id,
        public string $relationship,
        public string $operation,
    ) {
    }
}
