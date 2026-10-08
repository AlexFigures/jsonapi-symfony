<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Error;

use Symfony\Component\Uid\Uuid;

/** @internal */
class CorrelationIdProvider
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
