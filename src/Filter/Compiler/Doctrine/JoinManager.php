<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Compiler\Doctrine;

use Doctrine\ORM\QueryBuilder;

/**
 * Minimal join manager stub. Ensures structure exists for future logic.
 * @internal
 */
final readonly class JoinManager
{
    public function __construct(
        private QueryBuilder $qb,
    ) {
    }

    public function resolveAlias(string $fieldPath): string
    {
        // TODO: add join resolution logic once metadata integration lands.
        return $this->qb->getRootAliases()[0] ?? 'root';
    }
}
