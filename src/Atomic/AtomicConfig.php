<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Atomic;

final class AtomicConfig
{
    public function __construct(
        public bool $enabled = false,
        public string $endpoint = '/api/operations',
        public bool $requireExtHeader = true,
        public int $maxOperations = 100,
        public string $returnPolicy = 'auto',
        public bool $allowHref = true,
        public bool $lidInResourceAndIdentifier = true,
        public string $routePrefix = '/api',
    ) {
    }
}
