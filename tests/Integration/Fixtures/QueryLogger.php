<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures;

use Psr\Log\AbstractLogger;

final class QueryLogger extends AbstractLogger
{
    public ?object $observer = null;

    public function log($level, $message, array $context = []): void
    {
        if ($this->observer === null) {
            return;
        }
        $sql = $context['sql'] ?? match ($message) {
            'Beginning transaction' => 'START TRANSACTION',
            'Committing transaction' => 'COMMIT',
            'Rolling back transaction' => 'ROLLBACK',
            default => null,
        };
        if ($sql !== null) {
            $this->observer->startQuery($sql, $context['params'] ?? null, $context['types'] ?? null);
        }
    }
}
