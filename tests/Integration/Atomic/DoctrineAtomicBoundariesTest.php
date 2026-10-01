<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Http\Exception\JsonApiHttpException;
use AlexFigures\Symfony\Http\Exception\UnsupportedTransactionBoundaryException;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Concurrency\ObservedEntityManager;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\GeneratedRecord;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;

final class DoctrineAtomicBoundariesTest extends DoctrineAtomicTestCase
{
    public function testPostgresBatchIgnoresUnrelatedMysqlCommitFault(): void
    {
        $other = $this->otherManager('mysql');
        try {
            $response = $this->executeAtomicRequest([
                ['op' => 'add', 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'Postgres only']]],
            ]);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM generated_records'));
            self::assertSame([0, 0, 0], [$other->begins, $other->flushes, $other->commits]);
            self::assertFalse($other->getConnection()->isConnected());
        } finally {
            $other->getConnection()->close();
        }
    }

    #[DataProvider('boundaries')]
    public function testCrossDatabaseAndShardAtomicPreflightPreservesEveryOriginal(string $database): void
    {
        $other = $this->otherManager($database);
        $schema = new SchemaTool($other);
        $metadata = [$other->getClassMetadata(Author::class)];
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $record = new GeneratedRecord();
        $record->name = 'Original root';
        $this->em->persist($record);
        $this->em->flush();
        $author = new Author();
        $author->setName('Original other');
        $author->setEmail('other@example.com');
        $other->persist($author);
        $other->flush();
        $other->flushes = 0;
        $queriesA = new DebugStack();
        $queriesB = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($queriesA);
        $other->getConnection()->getConfiguration()->setSQLLogger($queriesB);
        try {
            try {
                $this->executeAtomicRequest([
                    ['op' => 'update', 'ref' => ['type' => 'generated-records', 'id' => (string) $record->id], 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'Mutated first']]],
                    ['op' => 'update', 'ref' => ['type' => 'authors', 'id' => $author->getId()], 'data' => ['type' => 'authors', 'attributes' => ['name' => 'Mutated second']]],
                ]);
                self::fail('The complete batch must be rejected.');
            } catch (UnsupportedTransactionBoundaryException $exception) {
                self::assertSame(409, $exception->getStatusCode());
            }
            self::assertCount(0, $queriesA->queries, 'No SQL or flush on the first boundary.');
            self::assertCount(0, $queriesB->queries, 'No SQL or flush on the second boundary.');
            self::assertSame([0, 0, 0], [$other->begins, $other->flushes, $other->commits]);
            self::assertSame('Original root', $this->em->getConnection()->fetchOne('SELECT name FROM generated_records WHERE id = ?', [$record->id]));
            self::assertSame('Original other', $other->getConnection()->fetchOne('SELECT name FROM authors WHERE id = ?', [$author->getId()]));
        } finally {
            $schema->dropSchema($metadata);
            $other->getConnection()->close();
        }
    }

    public static function boundaries(): iterable
    {
        yield 'PostgreSQL + MySQL' => ['mysql'];
        yield 'independent PostgreSQL shard connections' => ['postgres'];
    }

    public function testInvalidLinkageTypeIsRejectedBeforeBoundaryLookupAndSql(): void
    {
        $queries = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($queries);
        try {
            $this->executeAtomicRequest([
                ['op' => 'add', 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'First']]],
                ['op' => 'add', 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'Second'], 'relationships' => ['parent' => ['data' => ['type' => 'unknown-resource', 'id' => '1']]]]],
            ]);
            self::fail('Expected a linkage type error.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(409, $exception->getStatusCode());
            self::assertSame('type-mismatch', $exception->getErrors()[0]->code);
            self::assertSame('/atomic:operations/1/data/relationships/parent/data/type', $exception->getErrors()[0]->source->pointer);
        }
        self::assertCount(0, $queries->queries);
    }

    public function testGeneratedIdsAndLidsRollbackAfterLaterFailure(): void
    {
        try {
            $this->executeAtomicRequest([
                ['op' => 'add', 'data' => ['type' => 'generated-records', 'lid' => 'first', 'attributes' => ['name' => 'First']]],
                ['op' => 'add', 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'Second'], 'relationships' => ['parent' => ['data' => ['type' => 'generated-records', 'lid' => 'first']]]]],
                ['op' => 'update', 'ref' => ['type' => 'generated-records', 'id' => '2147483647'], 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'Missing']]],
            ]);
            self::fail('Expected later operation failure.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
        }
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM generated_records'));
    }

    private function otherManager(string $database): ObservedEntityManager
    {
        $params = $database === 'mysql'
            ? ['url' => $_ENV['DATABASE_URL_MYSQL']]
            : ['driver' => 'pdo_pgsql', 'host' => 'postgres', 'user' => 'jsonapi', 'password' => 'secret', 'dbname' => 'jsonapi_boundary_shard'];
        if ($database === 'postgres') {
            $admin = DriverManager::getConnection(['url' => $this->getDatabaseUrl()]);
            if (!$admin->fetchOne("SELECT 1 FROM pg_database WHERE datname = 'jsonapi_boundary_shard'")) {
                $admin->executeStatement('CREATE DATABASE jsonapi_boundary_shard');
            }
            $admin->close();
        }
        $other = new ObservedEntityManager(DriverManager::getConnection($params), ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__) . '/Fixtures/Entity'], true));
        $this->managerRegistry->registerManager('other', $other);
        $this->managerRegistry->mapClassToManager(Author::class, 'other');
        return $other;
    }
}
