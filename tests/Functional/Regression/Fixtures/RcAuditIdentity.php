<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Regression\Fixtures;

final class RcAuditIdentity
{
    public function __invoke(): string
    {
        return 'constructor-di@example.test';
    }
}
