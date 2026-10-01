<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Discovery;

use AlexFigures\Symfony\Bridge\Doctrine\Identifier\DoctrineIdentifierMetadataValidator;
use AlexFigures\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistry;
use AlexFigures\Symfony\Tests\Fixtures\Doctrine\TestManagerRegistry;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Discovery\RegionalQuota;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\GeneratedRecord;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\TypedIdentifierRecord;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DoctrineIdentifierDiscoveryTest extends TestCase
{
    #[DataProvider('identifiers')]
    public function testDiscoveryAcceptsSingleIdentifier(string $class, string $type): void
    {
        $loader = $this->loader($class);
        self::assertNotNull($loader->load('.', 'jsonapi')->get('jsonapi.' . $type . '.show'));
    }

    public static function identifiers(): iterable
    {
        yield 'integer' => [GeneratedRecord::class, 'generated-records'];
        yield 'UUID' => [TypedIdentifierRecord::class, 'typed-records'];
        yield 'natural string' => [Author::class, 'authors'];
    }

    public function testCompositeIdFailsDuringRouteDiscoveryWithoutConnectingToDatabase(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('JSON:API resource "regional-quotas" maps to entity ' . RegionalQuota::class . ', which uses a composite Doctrine identifier. Composite identifiers are not currently supported');
        $this->loader(RegionalQuota::class)->load('.', 'jsonapi');
    }

    private function loader(string $class): JsonApiRouteLoader
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $manager = new EntityManager($connection, ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__) . '/Fixtures'], true));
        $validator = new DoctrineIdentifierMetadataValidator(new TestManagerRegistry(['default' => $manager]));
        return new JsonApiRouteLoader(new ResourceRegistry([$class]), metadataValidator: $validator);
    }
}
