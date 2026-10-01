<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge\Doctrine\Transaction;

use AlexFigures\Symfony\Atomic\Execution\AtomicTransaction;
use AlexFigures\Symfony\Atomic\Operation;
use AlexFigures\Symfony\Atomic\Ref;
use AlexFigures\Symfony\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager;
use AlexFigures\Symfony\Http\Exception\UnsupportedTransactionBoundaryException;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistry;
use AlexFigures\Symfony\Tests\Fixtures\Doctrine\TestManagerRegistry;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\GeneratedRecord;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DoctrineTransactionManagerTest extends TestCase
{
    public function testOnlySelectedManagerIsOpenedFlushedAndCommitted(): void
    {
        $selected = $this->createMock(EntityManagerInterface::class);
        $unrelated = $this->createMock(EntityManagerInterface::class);
        $selected->method('getConnection')->willReturn($this->createStub(Connection::class));
        $selected->expects(self::once())->method('beginTransaction');
        $selected->expects(self::once())->method('flush');
        $selected->expects(self::once())->method('commit');
        foreach (['beginTransaction', 'flush', 'commit', 'rollback', 'close'] as $method) {
            $unrelated->expects(self::never())->method($method);
        }
        $registry = new TestManagerRegistry(['default' => $unrelated, 'selected' => $selected], [GeneratedRecord::class => 'selected']);
        $flush = new FlushManager($registry);
        $transactions = new DoctrineTransactionManager($registry, $flush);
        self::assertSame('done', $transactions->transactionalFor([GeneratedRecord::class], function () use ($transactions, $flush): string {
            return $transactions->transactionalFor([GeneratedRecord::class], function () use ($flush): string {
                $flush->scheduleFlush(GeneratedRecord::class);
                return 'done';
            });
        }));
        self::assertFalse($flush->isFlushScheduled());
    }

    #[DataProvider('connections')]
    public function testAtomicPreflightRejectsIndependentManagersBeforeCallback(bool $sharedConnection): void
    {
        $a = $this->createMock(EntityManagerInterface::class);
        $b = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createStub(Connection::class);
        $a->method('getConnection')->willReturn($connection);
        $b->method('getConnection')->willReturn($sharedConnection ? $connection : $this->createStub(Connection::class));
        foreach ([$a, $b] as $manager) {
            foreach (['beginTransaction', 'flush', 'commit'] as $method) {
                $manager->expects(self::never())->method($method);
            }
        }
        $registry = new TestManagerRegistry(['default' => $a, 'b' => $b], [Author::class => 'b']);
        $atomic = new AtomicTransaction(new DoctrineTransactionManager($registry, new FlushManager($registry)), new ResourceRegistry([GeneratedRecord::class, Author::class]));
        $operations = [
            new Operation('update', new Ref('generated-records', '1', null, null), null, ['type' => 'generated-records'], [], '/atomic:operations/0'),
            new Operation('update', new Ref('authors', 'a', null, null), null, ['type' => 'authors'], [], '/atomic:operations/1'),
        ];
        try {
            $atomic->run(static fn () => self::fail('No operation may execute during failed preflight.'), $operations);
            self::fail('Expected boundary rejection.');
        } catch (UnsupportedTransactionBoundaryException $exception) {
            self::assertSame(409, $exception->getStatusCode());
            self::assertSame('409', $exception->getErrors()[0]->status);
            self::assertSame('unsupported-transaction-boundary', $exception->getErrors()[0]->code);
        }
    }

    public static function connections(): iterable
    {
        yield 'independent connections' => [false];
        yield 'shared connection, independent units of work' => [true];
    }

    public function testUnexpectedManagerCannotBeScheduledInsideScopedTransaction(): void
    {
        $a = $this->createMock(EntityManagerInterface::class);
        $b = $this->createMock(EntityManagerInterface::class);
        $a->expects(self::once())->method('rollback');
        $a->expects(self::once())->method('close');
        $a->expects(self::never())->method('commit');
        $b->expects(self::never())->method('flush');
        $registry = new TestManagerRegistry(['default' => $a, 'b' => $b], [Author::class => 'b']);
        $flush = new FlushManager($registry);
        $transactions = new DoctrineTransactionManager($registry, $flush);
        $this->expectException(UnsupportedTransactionBoundaryException::class);
        $transactions->transactionalFor([GeneratedRecord::class], static fn () => $flush->scheduleFlush(Author::class));
    }
}
