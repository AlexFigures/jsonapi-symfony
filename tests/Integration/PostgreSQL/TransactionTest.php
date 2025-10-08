<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\PostgreSQL;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;

final class TransactionTest extends DoctrineIntegrationTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES']
            ?? 'postgresql://jsonapi:secret@localhost:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    public function testTransactionCommitsOnSuccess(): void
    {
        $result = $this->transactionManager->transactional(function () {
            $changes = new ChangeSet(
                attributes: [
                    'title' => 'Transactional Article',
                    'content' => 'Content',
                ]
            );

            return $this->persister->create('articles', $changes, 'tx-article');
        });

        self::assertSame('tx-article', $result->getId());

        // Verify that the data was saved
        $this->em->clear();
        $found = $this->em->find(Article::class, 'tx-article');
        self::assertNotNull($found);
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->transactionManager->transactional(function () {
                $changes = new ChangeSet(
                    attributes: [
                        'title' => 'Will be rolled back',
                        'content' => 'Content',
                    ]
                );

                $this->persister->create('articles', $changes, 'rollback-article');

                // Throw an exception
                throw new \RuntimeException('Rollback test');
            });

            self::fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            self::assertSame('Rollback test', $e->getMessage());
        }

        // Verify that the data was NOT saved
        $this->em->clear();
        $found = $this->em->find(Article::class, 'rollback-article');
        self::assertNull($found);
    }

    public function testNestedTransactions(): void
    {
        $result = $this->transactionManager->transactional(function () {
            $changes1 = new ChangeSet(attributes: ['title' => 'Article 1', 'content' => 'Content 1']);
            $article1 = $this->persister->create('articles', $changes1, 'nested-1');

            // Nested transaction
            $this->transactionManager->transactional(function () {
                $changes2 = new ChangeSet(attributes: ['title' => 'Article 2', 'content' => 'Content 2']);
                $this->persister->create('articles', $changes2, 'nested-2');
            });

            return $article1;
        });

        // Verify that both articles were saved
        $this->em->clear();
        self::assertNotNull($this->em->find(Article::class, 'nested-1'));
        self::assertNotNull($this->em->find(Article::class, 'nested-2'));
    }
}
