<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Discovery;

use AlexFigures\JsonApi\Bridge\Doctrine\Identifier\DoctrineIdentifierMetadataValidator;
use AlexFigures\JsonApi\Bridge\Symfony\Routing\JsonApiRouteLoader;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistry;
use AlexFigures\JsonApi\Tests\Fixtures\Doctrine\TestManagerRegistry;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Discovery\RegionalQuota;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\GeneratedRecord;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\TypedIdentifierRecord;
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
        $connection = \AlexFigures\JsonApi\Tests\Integration\Fixtures\ConnectionFactory::create(['driver' => 'pdo_sqlite', 'memory' => true]);
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__) . '/Fixtures'], true);
        \AlexFigures\JsonApi\Tests\Integration\Fixtures\DoctrineConfiguration::configureLazyObjects($config);
        $manager = new EntityManager($connection, $config);
        $validator = new DoctrineIdentifierMetadataValidator(new TestManagerRegistry(['default' => $manager]));
        return new JsonApiRouteLoader(new ResourceRegistry([$class]), metadataValidator: $validator);
    }
}
