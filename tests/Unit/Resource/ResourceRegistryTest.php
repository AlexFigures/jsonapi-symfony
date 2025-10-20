<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Resource;

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
        $registry = new ResourceRegistry([ArticleFixture::class]);

        $metadata = $registry->getByType('articles');

        self::assertSame('articles', $metadata->type);
        self::assertSame(ArticleFixture::class, $metadata->class);
    }

    public function testGetByTypeThrowsWhenTypeNotFound(): void
    {
        $registry = new ResourceRegistry([ArticleFixture::class]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown resource type "unknown".');

        $registry->getByType('unknown');
    }

    public function testHasTypeReturnsTrueWhenExists(): void
    {
        $registry = new ResourceRegistry([ArticleFixture::class]);

        self::assertTrue($registry->hasType('articles'));
    }

    public function testHasTypeReturnsFalseWhenNotExists(): void
    {
        $registry = new ResourceRegistry([ArticleFixture::class]);

        self::assertFalse($registry->hasType('unknown'));
    }

    public function testGetByClassReturnsMetadataWhenFound(): void
    {
        $registry = new ResourceRegistry([ArticleFixture::class]);

        $metadata = $registry->getByClass(ArticleFixture::class);

        self::assertNotNull($metadata);
        self::assertSame('articles', $metadata->type);
        self::assertSame(ArticleFixture::class, $metadata->class);
    }

    public function testGetByClassReturnsNullWhenNotFound(): void
    {
        $registry = new ResourceRegistry([ArticleFixture::class]);

        $metadata = $registry->getByClass(AuthorFixtureForRegistry::class);

        self::assertNull($metadata);
    }

    public function testAllReturnsListOfAllMetadata(): void
    {
        $registry = new ResourceRegistry([
            ArticleFixture::class,
            AuthorFixtureForRegistry::class,
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
            ArticleFixture::class,
            DuplicateArticleFixtureForRegistry::class,
        ]);
    }

    public function testThrowsWhenResourceTypeMismatch(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Resource type mismatch/');

        new ResourceRegistry([
            'wrong-type' => ArticleFixture::class,
        ]);
    }

    public function testThrowsWhenConfiguredDataClassDoesNotExist(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Class "NonExistentDataClass" configured for dataClass for resource');

        new ResourceRegistry([InvalidDataClassFixture::class]);
    }

    public function testThrowsWhenConfiguredViewClassDoesNotExist(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Class "NonExistentViewClass" configured for viewClass for resource');

        new ResourceRegistry([InvalidViewClassFixture::class]);
    }

    public function testAcceptsResourceInstances(): void
    {
        $registry = new ResourceRegistry([new ArticleFixture()]);

        $metadata = $registry->getByType('articles');

        self::assertSame('articles', $metadata->type);
        self::assertSame(ArticleFixture::class, $metadata->class);
    }

    public function testAcceptsResourceClassStrings(): void
    {
        $registry = new ResourceRegistry([ArticleFixture::class]);

        $metadata = $registry->getByType('articles');

        self::assertSame('articles', $metadata->type);
        self::assertSame(ArticleFixture::class, $metadata->class);
    }

    public function testAcceptsMixedResourceInstancesAndClassStrings(): void
    {
        $registry = new ResourceRegistry([
            new ArticleFixture(),
            AuthorFixtureForRegistry::class,
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

        $metadata = $registry->getByClass(ArticleFixture::class);

        self::assertNull($metadata);
    }

    public function testGetByTypeWithEmptyRegistryThrows(): void
    {
        $registry = new ResourceRegistry([]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown resource type "articles".');

        $registry->getByType('articles');
    }
}

#[JsonApiResource(type: 'articles')]
final class ArticleFixture
{
}

#[JsonApiResource(type: 'authors')]
final class AuthorFixtureForRegistry
{
}

#[JsonApiResource(type: 'articles')]
final class DuplicateArticleFixtureForRegistry
{
}

#[JsonApiResource(type: 'invalid-data', dataClass: 'NonExistentDataClass')]
final class InvalidDataClassFixture
{
}

#[JsonApiResource(type: 'invalid-view', viewClass: 'NonExistentViewClass')]
final class InvalidViewClassFixture
{
}
