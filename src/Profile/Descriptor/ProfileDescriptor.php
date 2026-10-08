<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Descriptor;

/** @api */
final readonly class ProfileDescriptor
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public string $uri,
        public string $name,
        public string $version,
        public ?string $documentationUrl = null,
        public string $description = '',
        public array $capabilities = [],
    ) {
    }
}
