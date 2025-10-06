<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Profile\Descriptor;

final class ProfileDescriptor
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $name,
        public readonly string $version,
        public readonly ?string $documentationUrl = null,
        public readonly string $description = '',
        public readonly array $capabilities = [],
    ) {
    }
}
