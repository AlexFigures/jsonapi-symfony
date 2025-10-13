<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Tx;

/**
 * Manages database transactions for write operations.
 *
 * Implement this interface to wrap write operations in database transactions,
 * ensuring atomicity and consistency.
 *
 * The bundle automatically wraps all write operations (POST, PATCH, DELETE)
 * in a transaction using this interface.
 *
 * Example implementation for Doctrine ORM:
 * ```php
 * final class DoctrineTransactionManager implements TransactionManager
 * {
 *     public function __construct(private EntityManagerInterface $em) {}
 *
 *     public function transactional(callable $callback)
 *     {
 *         return $this->em->transactional($callback);
 *     }
 * }
 * ```
 *
 * Example implementation for PDO:
 * ```php
 * final class PdoTransactionManager implements TransactionManager
 * {
 *     public function __construct(private PDO $pdo) {}
 *
 *     public function transactional(callable $callback)
 *     {
 *         $this->pdo->beginTransaction();
 *         try {
 *             $result = $callback();
 *             $this->pdo->commit();
 *             return $result;
 *         } catch (\Throwable $e) {
 *             $this->pdo->rollBack();
 *             throw $e;
 *         }
 *     }
 * }
 * ```
 *
 * @api This interface is part of the public API and follows semantic versioning.
 * @since 0.1.0
 */
interface TransactionManager
{
    /**
     * Execute a callback within a database transaction.
     *
     * The transaction should be committed if the callback completes successfully,
     * or rolled back if an exception is thrown.
     *
     * @template T
     * @param  callable():T $callback Callback to execute within the transaction
     * @return T            The return value of the callback
     * @throws \Throwable   If the callback throws an exception (after rollback)
     */
    public function transactional(callable $callback);
}
