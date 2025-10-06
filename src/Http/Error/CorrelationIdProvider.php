<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Error;

use Symfony\Component\Uid\Uuid;

class CorrelationIdProvider
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
