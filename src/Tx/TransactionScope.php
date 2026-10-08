<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tx;

use AlexFigures\JsonApi\Contract\Tx\ScopedTransactionManagerInterface;
use AlexFigures\JsonApi\Contract\Tx\TransactionManager;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;

/** @internal Adapts optional scoped transactions while retaining custom providers' contract. */
final class TransactionScope
{
    /** @template T
     * @param  callable():T $callback
     * @return T
     */
    public static function write(TransactionManager $transactions, ResourceRegistryInterface $resources, string $type, callable $callback): mixed
    {
        if ($transactions instanceof \AlexFigures\JsonApi\Contract\Tx\ResourceWriteTransactionManagerInterface) {
            return $transactions->transactionalWriteFor($type, $resources->getByType($type)->dataClass, $callback);
        }
        return self::run($transactions, $resources, [$type], $callback);
    }

    /** @template T
     * @param  list<string> $types
     * @param  callable():T $callback
     * @return T
     */
    public static function run(TransactionManager $transactions, ResourceRegistryInterface $resources, array $types, callable $callback): mixed
    {
        if (!$transactions instanceof ScopedTransactionManagerInterface) {
            return $transactions->transactional($callback);
        }
        $classes = [];
        foreach (array_unique($types) as $type) {
            $classes[] = $resources->getByType($type)->dataClass;
        }

        return $transactions->transactionalFor(array_values(array_unique($classes)), $callback);
    }
}
