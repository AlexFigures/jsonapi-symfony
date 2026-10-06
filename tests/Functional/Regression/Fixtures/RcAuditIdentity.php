<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression\Fixtures;

final class RcAuditIdentity
{
    public function __invoke(): string
    {
        return 'constructor-di@example.test';
    }
}
