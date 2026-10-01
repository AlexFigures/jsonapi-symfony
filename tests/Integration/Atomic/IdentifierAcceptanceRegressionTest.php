<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Atomic\Execution\Handlers\AddHandler;
use AlexFigures\Symfony\Atomic\Lid\LidRegistry;
use AlexFigures\Symfony\Atomic\Operation;
use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Http\Exception\JsonApiHttpException;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\TypedIdentifierRecord;
use Symfony\Component\Uid\Uuid;

final class IdentifierAcceptanceRegressionTest extends DoctrineAtomicTestCase
{
    public function testTypedClientIdentifierIsConvertedForOrdinaryAndAtomicCreates(): void
    {
        $ordinaryId = Uuid::v4()->toRfc4122();
        $ordinary = $this->transactionManager->transactional(fn () => $this->validatingProcessor->processCreate('typed-records', new ChangeSet(['name' => 'ordinary']), $ordinaryId));
        self::assertInstanceOf(Uuid::class, $ordinary->id);
        $atomicId = Uuid::v4()->toRfc4122();
        $handler = new AddHandler($this->validatingProcessor, $this->changeSetFactory, $this->registry, $this->accessor, $this->flushManager, new WriteConfig(true, ['typed-records' => true]));
        $operation = new Operation('add', null, null, ['type' => 'typed-records', 'id' => $atomicId, 'attributes' => ['name' => 'atomic']], [], '/atomic:operations/0');
        $this->transactionManager->transactional(fn () => $handler->handle($operation, new LidRegistry()));
        $this->em->clear();
        self::assertSame('ordinary', $this->repository->findOne('typed-records', $ordinaryId, new Criteria())->name);
        self::assertSame('atomic', $this->repository->findOne('typed-records', $atomicId, new Criteria())->name);
        self::assertNull($this->repository->findOne('typed-records', Uuid::v4()->toRfc4122(), new Criteria()));
    }

    public function testMalformedRouteIdentifierProducesClientError(): void
    {
        try {
            $this->repository->findOne('typed-records', 'not-a-uuid', new Criteria());
            self::fail('Invalid identifier must be rejected.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(400, $exception->getStatusCode());
        }
    }

    public function testAtomicClientIdentifierPolicyRunsBeforeEntityAssignment(): void
    {
        $handler = new AddHandler($this->validatingProcessor, $this->changeSetFactory, $this->registry, $this->accessor, $this->flushManager, new WriteConfig(true));
        try {
            $handler->handle(new Operation('add', null, null, ['type' => 'typed-records', 'id' => Uuid::v4()->toRfc4122(), 'attributes' => ['name' => 'denied']], [], '/atomic:operations/0'), new LidRegistry());
            self::fail('Client-generated identifiers are disabled.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM typed_identifier_records'));
        }
    }
}
