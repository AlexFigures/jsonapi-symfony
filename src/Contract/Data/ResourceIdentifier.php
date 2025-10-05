<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

final class ResourceIdentifier
{
    public function __construct(
        public string $type,
        public string $id,
    ) {
    }
}
