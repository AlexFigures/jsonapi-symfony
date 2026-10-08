<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Events;

/** @api */
final readonly class ResourceChangedEvent
{
    public function __construct(
        public string $type,
        public string $id,
        public string $operation,
    ) {
    }
}
