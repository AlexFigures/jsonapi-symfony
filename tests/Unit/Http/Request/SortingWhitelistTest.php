<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Http\Request;

use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;

final class SortingWhitelistTest extends TestCase
{
    public function testAllowedForReturnsEmptyArrayWhenTypeNotInRegistry(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('hasType')->with('unknown')->willReturn(false);

        $whitelist = new SortingWhitelist($registry);

        self::assertSame([], $whitelist->allowedFor('unknown'));
    }

    public function testAllowedForReturnsFieldsFromAttribute(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [],
            sortableFields: ['title', 'createdAt', 'updatedAt', 'viewCount'],
        );

        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        $whitelist = new SortingWhitelist($registry);

        self::assertSame(
            ['title', 'createdAt', 'updatedAt', 'viewCount'],
            $whitelist->allowedFor('articles')
        );
    }

    public function testAllowedForReturnsEmptyWhenAttributeIsEmpty(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [],
            sortableFields: [], // Empty attribute
        );

        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        $whitelist = new SortingWhitelist($registry);

        self::assertSame([], $whitelist->allowedFor('articles'));
    }

    public function testAllowedForWorksWithMultipleTypes(): void
    {
        $articlesMetadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [],
            sortableFields: ['title', 'createdAt'],
        );

        $authorsMetadata = new ResourceMetadata(
            type: 'authors',
            class: AuthorFixture::class,
            attributes: [],
            relationships: [],
            sortableFields: ['name', 'email'],
        );

        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('hasType')->willReturnCallback(
            fn (string $type) => in_array($type, ['articles', 'authors'], true)
        );
        $registry->method('getByType')->willReturnCallback(
            fn (string $type) => match ($type) {
                'articles' => $articlesMetadata,
                'authors' => $authorsMetadata,
            }
        );

        $whitelist = new SortingWhitelist($registry);

        self::assertSame(['title', 'createdAt'], $whitelist->allowedFor('articles'));
        self::assertSame(['name', 'email'], $whitelist->allowedFor('authors'));
        self::assertSame([], $whitelist->allowedFor('unknown'));
    }
}

#[JsonApiResource(type: 'authors')]
final class AuthorFixture
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $name;

    #[Attribute]
    public string $email;
}
