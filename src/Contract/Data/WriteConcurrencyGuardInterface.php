<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Contract\Data;

/**
 * Protect current-validator evaluation and mutation as one persistence operation.
 * The callback must rebuild its validator after protection is acquired.
 * Implementations retain protection until the write is flushed and committed.
 * @api
 */
interface WriteConcurrencyGuardInterface
{
    /** @template T
     * @param  callable():T $write
     * @return T
     */
    public function protect(string $type, string $id, callable $write): mixed;
}
