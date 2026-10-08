<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Resource\Write;

/** @api */
final readonly class WriteContext
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public ?object $user = null,
        public array $options = [],
    ) {
    }
}
