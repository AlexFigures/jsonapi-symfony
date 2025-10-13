<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Compiler\Doctrine;

use Doctrine\ORM\QueryBuilder;

/**
 * Minimal join manager stub. Ensures structure exists for future logic.
 */
final class JoinManager
{
    public function __construct(
        private readonly QueryBuilder $qb,
    ) {
    }

    public function resolveAlias(string $fieldPath): string
    {
        // TODO: add join resolution logic once metadata integration lands.
        return $this->qb->getRootAliases()[0] ?? 'root';
    }
}
