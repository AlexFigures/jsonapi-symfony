<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Http\Request\SortingWhitelist;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;
use JsonApi\Symfony\Tests\Fixtures\Model\Tag;
use PHPUnit\Framework\TestCase;

final class SortableFieldsAttributeTest extends TestCase
{
    private ResourceRegistry $registry;
    private SortingWhitelist $whitelist;

    protected function setUp(): void
    {
        $this->registry = new ResourceRegistry([
            Article::class,
            Author::class,
            Tag::class,
        ]);

        $this->whitelist = new SortingWhitelist($this->registry);
    }

    public function testArticleHasSortableFields(): void
    {
        $metadata = $this->registry->getByType('articles');

        self::assertSame(['title', 'createdAt'], $metadata->sortableFields);
    }

    public function testAuthorHasSortableFields(): void
    {
        $metadata = $this->registry->getByType('authors');

        self::assertSame(['name'], $metadata->sortableFields);
    }

    public function testTagHasSortableFields(): void
    {
        $metadata = $this->registry->getByType('tags');

        self::assertSame(['name'], $metadata->sortableFields);
    }

    public function testSortingWhitelistUsesAttributeConfiguration(): void
    {
        self::assertSame(['title', 'createdAt'], $this->whitelist->allowedFor('articles'));
        self::assertSame(['name'], $this->whitelist->allowedFor('authors'));
        self::assertSame(['name'], $this->whitelist->allowedFor('tags'));
    }

    public function testSortingWhitelistReturnsEmptyForUnknownType(): void
    {
        self::assertSame([], $this->whitelist->allowedFor('unknown'));
    }
}

