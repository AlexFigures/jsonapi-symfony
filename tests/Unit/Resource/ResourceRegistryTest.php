<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Resource;

use AlexFigures\Symfony\Resource\Attribute\JsonApiDto;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistry;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceRegistry::class)]
final class ResourceRegistryTest extends TestCase
{
    public function testGetByTypeReturnsMetadataWhenFound(): void
    {
        $registry = new ResourceRegistry([ResourceRegistryArticleFixture::class]);

        $metadata = $registry->getByType('articles');

        self::assertSame('articles', $metadata->type);
        self::assertSame(ResourceRegistryArticleFixture::class, $metadata->class);
    }

    public function testGetByTypeThrowsWhenTypeNotFound(): void
    {
        $registry = new ResourceRegistry([ResourceRegistryArticleFixture::class]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown resource type "unknown".');

        $registry->getByType('unknown');
    }

    public function testHasTypeReturnsTrueWhenExists(): void
    {
        $registry = new ResourceRegistry([ResourceRegistryArticleFixture::class]);

        self::assertTrue($registry->hasType('articles'));
    }

    public function testHasTypeReturnsFalseWhenNotExists(): void
    {
        $registry = new ResourceRegistry([ResourceRegistryArticleFixture::class]);

        self::assertFalse($registry->hasType('unknown'));
    }

    public function testGetByClassReturnsMetadataWhenFound(): void
    {
        $registry = new ResourceRegistry([ResourceRegistryArticleFixture::class]);

        $metadata = $registry->getByClass(ResourceRegistryArticleFixture::class);

        self::assertNotNull($metadata);
        self::assertSame('articles', $metadata->type);
        self::assertSame(ResourceRegistryArticleFixture::class, $metadata->class);
    }

    public function testGetByClassReturnsNullWhenNotFound(): void
    {
        $registry = new ResourceRegistry([ResourceRegistryArticleFixture::class]);

        $metadata = $registry->getByClass(ResourceRegistryAuthorFixture::class);

        self::assertNull($metadata);
    }

    public function testAllReturnsListOfAllMetadata(): void
    {
        $registry = new ResourceRegistry([
            ResourceRegistryArticleFixture::class,
            ResourceRegistryAuthorFixture::class,
        ]);

        $all = $registry->all();

        self::assertCount(2, $all);
        self::assertSame('articles', $all[0]->type);
        self::assertSame('authors', $all[1]->type);
    }

    public function testThrowsWhenResourceClassDoesNotExist(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Resource class "NonExistentClass" does not exist.');

        new ResourceRegistry(['NonExistentClass']);
    }

    public function testThrowsWhenResourceTypeIsAlreadyRegistered(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Resource type "articles" is already registered.');

        new ResourceRegistry([
            ResourceRegistryArticleFixture::class,
            ResourceRegistryDuplicateArticleFixture::class,
        ]);
    }

    public function testThrowsWhenResourceTypeMismatch(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Resource type mismatch/');

        new ResourceRegistry([
            'wrong-type' => ResourceRegistryArticleFixture::class,
        ]);
    }

    public function testAcceptsResourceInstances(): void
    {
        $registry = new ResourceRegistry([new ResourceRegistryArticleFixture()]);

        $metadata = $registry->getByType('articles');

        self::assertSame('articles', $metadata->type);
        self::assertSame(ResourceRegistryArticleFixture::class, $metadata->class);
    }

    public function testAcceptsResourceClassStrings(): void
    {
        $registry = new ResourceRegistry([ResourceRegistryArticleFixture::class]);

        $metadata = $registry->getByType('articles');

        self::assertSame('articles', $metadata->type);
        self::assertSame(ResourceRegistryArticleFixture::class, $metadata->class);
    }

    public function testAcceptsMixedResourceInstancesAndClassStrings(): void
    {
        $registry = new ResourceRegistry([
            new ResourceRegistryArticleFixture(),
            ResourceRegistryAuthorFixture::class,
        ]);

        self::assertTrue($registry->hasType('articles'));
        self::assertTrue($registry->hasType('authors'));
        self::assertCount(2, $registry->all());
    }

    public function testHasTypeWithEmptyRegistry(): void
    {
        $registry = new ResourceRegistry([]);

        self::assertFalse($registry->hasType('articles'));
    }

    public function testAllWithEmptyRegistry(): void
    {
        $registry = new ResourceRegistry([]);

        $all = $registry->all();

        self::assertSame([], $all);
    }

    public function testGetByClassWithEmptyRegistry(): void
    {
        $registry = new ResourceRegistry([]);

        $metadata = $registry->getByClass(ResourceRegistryArticleFixture::class);

        self::assertNull($metadata);
    }

    public function testGetByTypeWithEmptyRegistryThrows(): void
    {
        $registry = new ResourceRegistry([]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown resource type "articles".');

        $registry->getByType('articles');
    }

    public function testRegistersDtoMappings(): void
    {
        $registry = new ResourceRegistry([ResourceRegistryArticleWithDtoFixture::class]);

        $metadata = $registry->getByType('articles');

        self::assertSame([
            'v1' => ResourceRegistryArticleDtoFixture::class,
        ], $metadata->dtoClasses);
        self::assertSame(ResourceRegistryArticleDtoFixture::class, $metadata->getDtoClass('v1'));
        self::assertNull($metadata->getDtoClass(null));
    }
}

#[JsonApiResource(type: 'articles')]
final class ResourceRegistryArticleFixture
{
}

#[JsonApiResource(type: 'authors')]
final class ResourceRegistryAuthorFixture
{
}

#[JsonApiResource(type: 'articles')]
final class ResourceRegistryDuplicateArticleFixture
{
}

#[JsonApiResource(type: 'articles')]
#[JsonApiDto(version: 'v1', class: ResourceRegistryArticleDtoFixture::class)]
final class ResourceRegistryArticleWithDtoFixture
{
}

final class ResourceRegistryArticleDtoFixture
{
    public function __construct(public string $title = '')
    {
    }
}
