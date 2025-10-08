<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Bridge\Symfony\Routing;

use JsonApi\Symfony\Bridge\Symfony\Routing\RouteNameGenerator;
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
}
