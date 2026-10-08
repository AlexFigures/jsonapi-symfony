<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Atomic;

use AlexFigures\JsonApi\Http\Exception\JsonApiHttpException;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\GeneratedRecord;

/** PostgreSQL regressions for ATOMIC-001/002/004/007/008/011 and DOCTRINE-001/002. */
final class AcceptanceGapsTest extends DoctrineAtomicTestCase
{
    public function testGeneratedIdAndLidRelationshipAndOperationSnapshots(): void
    {
        $response = $this->executeAtomicRequest([
            ['op' => 'add', 'data' => ['type' => 'generated-records', 'lid' => 'first', 'attributes' => ['name' => 'First']]],
            ['op' => 'add', 'data' => ['type' => 'generated-records', 'lid' => 'second', 'attributes' => ['name' => 'Second'], 'relationships' => ['parent' => ['data' => ['type' => 'generated-records', 'lid' => 'first']]]]],
            ['op' => 'update', 'ref' => ['type' => 'generated-records', 'lid' => 'first'], 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'Changed']]],
            ['op' => 'update', 'ref' => ['type' => 'generated-records', 'lid' => 'second', 'relationship' => 'parent'], 'data' => null],
            ['op' => 'update', 'ref' => ['type' => 'generated-records', 'lid' => 'second', 'relationship' => 'parent'], 'data' => ['type' => 'generated-records', 'lid' => 'first']],
        ]);
        $document = json_decode((string) $response->getContent(), false, flags: \JSON_THROW_ON_ERROR);
        $results = $document->{'atomic:results'};
        self::assertSame('First', $results[0]->data->attributes->name);
        self::assertSame('Changed', $results[2]->data->attributes->name);
        self::assertInstanceOf(\stdClass::class, $results[3]);
        $id = $results[0]->data->id;
        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $id);
        $this->em->clear();
        self::assertSame('Changed', $this->em->find(GeneratedRecord::class, $id)->name);
        self::assertSame((int) $id, $this->em->find(GeneratedRecord::class, $results[1]->data->id)->parent->id);
    }

    public function testGeneratedCreateRollsBackOnLaterUniqueConflict(): void
    {
        try {
            $this->executeAtomicRequest([
                ['op' => 'add', 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'Duplicate']]],
                ['op' => 'add', 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'Duplicate']]],
            ]);
            self::fail('Expected unique conflict.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(409, $exception->getStatusCode());
            self::assertStringStartsWith('/atomic:operations/1/data', $exception->getErrors()[0]->source->pointer);
        }
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM generated_records'));
    }


    public function testDeferredUniqueConstraintMapsAtTransactionCompletion(): void
    {
        $connection = $this->em->getConnection();
        $index = $connection->fetchOne("SELECT indexname FROM pg_indexes WHERE tablename = 'generated_records' AND indexdef LIKE 'CREATE UNIQUE INDEX%' AND indexdef LIKE '%(name)%'");
        self::assertIsString($index);
        $connection->executeStatement('DROP INDEX ' . $connection->quoteIdentifier($index));
        $connection->executeStatement('ALTER TABLE generated_records ADD CONSTRAINT deferred_name UNIQUE (name) DEFERRABLE INITIALLY DEFERRED');
        try {
            $this->executeAtomicRequest([
                ['op' => 'add', 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'Duplicate']]],
                ['op' => 'add', 'data' => ['type' => 'generated-records', 'attributes' => ['name' => 'Duplicate']]],
            ]);
            self::fail('The deferred constraint must reject the transaction.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(409, $exception->getStatusCode());
            self::assertSame('/atomic:operations', $exception->getErrors()[0]->source->pointer);
        }
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM generated_records'));
    }

    public function testForeignKeyDeleteRollsBack(): void
    {
        $parent = new GeneratedRecord();
        $parent->name = 'Parent';
        $child = new GeneratedRecord();
        $child->name = 'Child';
        $child->parent = $parent;
        $this->em->persist($parent);
        $this->em->persist($child);
        $this->em->flush();
        $id = $parent->id;
        try {
            $this->executeAtomicRequest([['op' => 'remove', 'ref' => ['type' => 'generated-records', 'id' => (string) $id]]]);
            self::fail('Expected foreign key rejection.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(422, $exception->getStatusCode());
        }
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM generated_records WHERE id = ?', [$id]));
    }

    public function testUpdateTargetInferredAndIdentifierMismatchRejected(): void
    {
        $model = new GeneratedRecord();
        $model->name = 'Original';
        $this->em->persist($model);
        $this->em->flush();
        $data = ['type' => 'generated-records', 'id' => (string) $model->id, 'attributes' => ['name' => 'Updated']];
        $response = $this->executeAtomicRequest([['op' => 'update', 'data' => $data]]);
        self::assertSame(200, $response->getStatusCode());
        try {
            $this->executeAtomicRequest([['op' => 'update', 'ref' => ['type' => 'generated-records', 'id' => '999'], 'data' => $data]]);
            self::fail('Expected identifier mismatch.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(400, $exception->getStatusCode());
        }
    }
}
