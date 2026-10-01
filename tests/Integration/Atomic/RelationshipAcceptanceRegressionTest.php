<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Atomic;

use AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler;
use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Http\Exception\JsonApiHttpException;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\GeneratedRecord;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\RequiredChild;
use PHPUnit\Framework\Attributes\DataProvider;

final class RelationshipAcceptanceRegressionTest extends DoctrineAtomicTestCase
{
    #[DataProvider('writeKinds')]
    public function testRequiredLinkageRejectedBeforePersistence(string $kind): void
    {
        $parent = new GeneratedRecord();
        $parent->name = 'Parent';
        $child = new RequiredChild();
        $child->parent = $parent;
        $this->em->persist($parent);
        $this->em->persist($child);
        $this->em->flush();
        $id = (string) $child->id;
        try {
            if ($kind === 'resource') {
                $this->transactionManager->transactional(fn () => $this->validatingProcessor->processUpdate('required-children', $id, new ChangeSet(relationships: ['parent' => ['data' => null]])));
            } else {
                $handler = new GenericDoctrineRelationshipHandler($this->managerRegistry, $this->registry, $this->accessor, $this->flushManager);
                $this->transactionManager->transactional(fn () => $handler->replaceToOne('required-children', $id, 'parent', null));
            }
            self::fail('Required linkage must be validated before a SQL NOT NULL failure.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(422, $exception->getStatusCode());
        }
        self::assertSame($parent->id, (int) $this->em->getConnection()->fetchOne('SELECT parent_id FROM required_children WHERE id = ?', [$id]));
    }

    public static function writeKinds(): iterable
    {
        yield ['resource'];
        yield ['relationship'];
    }

    public function testMissingTargetPointsToPublicIdentifier(): void
    {
        $record = new GeneratedRecord();
        $record->name = 'Example';
        $this->em->persist($record);
        $this->em->flush();
        try {
            $this->validatingProcessor->processUpdate('generated-records', (string) $record->id, new ChangeSet(relationships: ['parent' => ['data' => ['type' => 'generated-records', 'id' => '999999']]]));
            self::fail('Missing relationship target must be rejected.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame('/data/relationships/parent/data/id', $exception->getErrors()[0]->source->pointer);
        }
    }
}
