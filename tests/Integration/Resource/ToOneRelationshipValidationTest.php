<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Resource;

use AlexFigures\JsonApi\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\JsonApi\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\JsonApi\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor;
use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Http\Error\ErrorBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Validation\ConstraintViolationMapper;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistry;
use AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver;
use AlexFigures\JsonApi\Tests\Fixtures\Doctrine\TestManagerRegistry;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Relationship\EagerNode;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ToOneRelationshipValidationTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([
            __DIR__.'/../Fixtures/Relationship',
        ], true);
        \AlexFigures\JsonApi\Tests\Integration\Fixtures\DoctrineConfiguration::configureLazyObjects($config);
        $url = $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
        self::assertIsString($url);
        $this->em = new EntityManager(\AlexFigures\JsonApi\Tests\Integration\Fixtures\ConnectionFactory::create(['url' => $url]), $config);
        $schemaTool = new SchemaTool($this->em);
        $metadata = [$this->em->getClassMetadata(EagerNode::class)];
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $oldParent = new EagerNode('old-parent');
        $newParent = new EagerNode('new-parent');
        $child = new EagerNode('child');
        $child->setParent($oldParent);
        $child->setMentor($oldParent);
        foreach ([$oldParent, $newParent, $child] as $node) {
            $this->em->persist($node);
        }
        $this->em->flush();
        $this->em->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            (new SchemaTool($this->em))->dropSchema([$this->em->getClassMetadata(EagerNode::class)]);
            $this->em->close();
        }
        parent::tearDown();
    }

    /** @return iterable<string, array{?string, bool}> */
    public static function relationshipUpdates(): iterable
    {
        yield 'non-null with validation query' => ['new-parent', true];
        yield 'null with validation query' => [null, true];
        yield 'non-null without validation query' => ['new-parent', false];
        yield 'null without validation query' => [null, false];
    }

    #[DataProvider('relationshipUpdates')]
    public function testUpdatePreservesToOneDuringValidation(?string $parentId, bool $queryDuringValidation): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::once())->method('validate')->willReturnCallback(
            function (EagerNode $entity) use ($parentId, $queryDuringValidation): ConstraintViolationList {
                self::assertSame($parentId, $entity->getParent()?->id);
                if ($queryDuringValidation) {
                    // Like a uniqueness validator, query by a key excluding the changed association.
                    $this->em->getRepository(EagerNode::class)->findBy(['name' => $entity->name]);
                    self::assertSame('old-parent', $entity->getParent()?->id, 'The validation query must reproduce stale EAGER hydration.');
                }
                return new ConstraintViolationList();
            }
        );

        $processor = $this->createProcessor($validator);
        $updated = $processor->processUpdate('eager-nodes', 'child', new ChangeSet(relationships: [
            'guardian' => ['data' => $parentId === null ? null : ['type' => 'eager-nodes', 'id' => $parentId]],
        ]));
        self::assertInstanceOf(EagerNode::class, $updated);
        self::assertSame($parentId, $updated->getParent()?->id);

        $this->em->flush();
        $this->em->clear();
        $reloaded = $this->em->find(EagerNode::class, 'child');
        self::assertNotNull($reloaded);
        self::assertSame($parentId, $reloaded->getParent()?->id);
    }

    public function testBidirectionalToOneDoesNotDuplicateInverseCollection(): void
    {
        $newMentor = $this->em->find(EagerNode::class, 'new-parent');
        self::assertNotNull($newMentor);
        self::assertCount(0, $newMentor->getChildren());
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::once())->method('validate')->willReturn(new ConstraintViolationList());

        $updated = $this->createProcessor($validator)->processUpdate('eager-nodes', 'child', new ChangeSet(relationships: [
            'mentor' => ['data' => ['type' => 'eager-nodes', 'id' => 'new-parent']],
        ]));
        self::assertInstanceOf(EagerNode::class, $updated);
        self::assertSame($newMentor, $updated->getMentor());
        self::assertCount(1, $newMentor->getChildren());
        self::assertSame($updated, $newMentor->getChildren()->first());

        $this->em->flush();
        $this->em->clear();
        $reloaded = $this->em->find(EagerNode::class, 'child');
        self::assertNotNull($reloaded);
        self::assertSame('new-parent', $reloaded->getMentor()?->id);
    }

    public function testToManyOnlyUpdatePreservesOtherRelationships(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::once())->method('validate')->willReturn(new ConstraintViolationList());

        $updated = $this->createProcessor($validator)->processUpdate('eager-nodes', 'new-parent', new ChangeSet(relationships: [
            'children' => ['data' => [['type' => 'eager-nodes', 'id' => 'child']]],
        ]));
        self::assertInstanceOf(EagerNode::class, $updated);
        self::assertCount(1, $updated->getChildren());
        self::assertNull($updated->getParent());
        self::assertNull($updated->getMentor());

        $this->em->flush();
        $this->em->clear();
        $child = $this->em->find(EagerNode::class, 'child');
        self::assertNotNull($child);
        self::assertSame('new-parent', $child->getMentor()?->id);
        self::assertSame('old-parent', $child->getParent()?->id);
    }

    private function createProcessor(ValidatorInterface $validator): ValidatingDoctrineProcessor
    {
        $managerRegistry = new TestManagerRegistry(['default' => $this->em]);
        $registry = new ResourceRegistry([EagerNode::class]);
        $accessor = PropertyAccess::createPropertyAccessor();

        return new ValidatingDoctrineProcessor(
            $managerRegistry,
            $registry,
            $accessor,
            $validator,
            new ConstraintViolationMapper($registry, new ErrorMapper(new ErrorBuilder(false))),
            new SerializerEntityInstantiator($managerRegistry, $accessor),
            new RelationshipResolver($managerRegistry, $registry, $accessor),
            new FlushManager($managerRegistry),
        );
    }
}
