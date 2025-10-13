<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge\Symfony\Routing;

use AlexFigures\Symfony\Bridge\Symfony\Routing\RouteNameGenerator;
use PHPUnit\Framework\TestCase;

final class RouteNameGeneratorTest extends TestCase
{
    public function testSnakeCaseNamingConvention(): void
    {
        $generator = new RouteNameGenerator(RouteNameGenerator::SNAKE_CASE);

        // Test standard resource routes
        self::assertSame('jsonapi.articles.index', $generator->generateRouteName('articles', 'index'));
        self::assertSame('jsonapi.blog_posts.show', $generator->generateRouteName('blog_posts', 'show'));
        self::assertSame('jsonapi.user_profiles.create', $generator->generateRouteName('user_profiles', 'create'));

        // Test relationship routes
        self::assertSame(
            'jsonapi.articles.relationships.author.show',
            $generator->generateRouteName('articles', null, 'author', 'show')
        );
        self::assertSame(
            'jsonapi.blog_posts.relationships.tags.add',
            $generator->generateRouteName('blog_posts', null, 'tags', 'add')
        );

        // Test related resource routes
        self::assertSame(
            'jsonapi.articles.related.author',
            $generator->generateRouteName('articles', null, 'author')
        );
    }

    public function testKebabCaseNamingConvention(): void
    {
        $generator = new RouteNameGenerator(RouteNameGenerator::KEBAB_CASE);

        // Test standard resource routes
        self::assertSame('jsonapi.articles.index', $generator->generateRouteName('articles', 'index'));
        self::assertSame('jsonapi.blog-posts.show', $generator->generateRouteName('blog_posts', 'show'));
        self::assertSame('jsonapi.user-profiles.create', $generator->generateRouteName('user_profiles', 'create'));

        // Test relationship routes
        self::assertSame(
            'jsonapi.articles.relationships.author.show',
            $generator->generateRouteName('articles', null, 'author', 'show')
        );
        self::assertSame(
            'jsonapi.blog-posts.relationships.tags.add',
            $generator->generateRouteName('blog_posts', null, 'tags', 'add')
        );

        // Test related resource routes
        self::assertSame(
            'jsonapi.articles.related.author',
            $generator->generateRouteName('articles', null, 'author')
        );
    }

    public function testKebabCaseTransformation(): void
    {
        $generator = new RouteNameGenerator(RouteNameGenerator::KEBAB_CASE);

        // Test various input formats
        self::assertSame('jsonapi.blog-posts.index', $generator->generateRouteName('blog_posts', 'index'));
        self::assertSame('jsonapi.user-profiles.index', $generator->generateRouteName('userProfiles', 'index'));
        self::assertSame('jsonapi.user-profiles.index', $generator->generateRouteName('UserProfiles', 'index'));
        self::assertSame('jsonapi.blog-posts.index', $generator->generateRouteName('blog-posts', 'index'));
        self::assertSame('jsonapi.simple.index', $generator->generateRouteName('simple', 'index'));
    }

    public function testDefaultNamingConvention(): void
    {
        $generator = new RouteNameGenerator();

        // Should default to snake_case
        self::assertSame('jsonapi.blog_posts.index', $generator->generateRouteName('blog_posts', 'index'));
    }

    public function testThrowsExceptionForNullActionWithoutRelationship(): void
    {
        $generator = new RouteNameGenerator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Action cannot be null for non-relationship routes');

        $generator->generateRouteName('articles', null);
    }

    /**
     * Test that resource types with multiple underscores are handled correctly.
     *
     * This is important for real-world scenarios where resource types like
     * 'category_synonyms', 'product_variants', etc. are common.
     */
    public function testMultipleUnderscoresInResourceType(): void
    {
        // Test snake_case convention (should preserve underscores)
        $snakeGenerator = new RouteNameGenerator(RouteNameGenerator::SNAKE_CASE);
        self::assertSame('jsonapi.category_synonyms.index', $snakeGenerator->generateRouteName('category_synonyms', 'index'));
        self::assertSame('jsonapi.category_synonyms.show', $snakeGenerator->generateRouteName('category_synonyms', 'show'));
        self::assertSame('jsonapi.category_synonyms.create', $snakeGenerator->generateRouteName('category_synonyms', 'create'));
        self::assertSame(
            'jsonapi.category_synonyms.relationships.category.show',
            $snakeGenerator->generateRouteName('category_synonyms', null, 'category', 'show')
        );
        self::assertSame(
            'jsonapi.category_synonyms.related.category',
            $snakeGenerator->generateRouteName('category_synonyms', null, 'category')
        );

        // Test kebab-case convention (should convert underscores to hyphens)
        $kebabGenerator = new RouteNameGenerator(RouteNameGenerator::KEBAB_CASE);
        self::assertSame('jsonapi.category-synonyms.index', $kebabGenerator->generateRouteName('category_synonyms', 'index'));
        self::assertSame('jsonapi.category-synonyms.show', $kebabGenerator->generateRouteName('category_synonyms', 'show'));
        self::assertSame('jsonapi.category-synonyms.create', $kebabGenerator->generateRouteName('category_synonyms', 'create'));
        self::assertSame(
            'jsonapi.category-synonyms.relationships.category.show',
            $kebabGenerator->generateRouteName('category_synonyms', null, 'category', 'show')
        );
        self::assertSame(
            'jsonapi.category-synonyms.related.category',
            $kebabGenerator->generateRouteName('category_synonyms', null, 'category')
        );
    }
}
