<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Http\Request;

use AlexFigures\JsonApi\Filter\Ast\Between;
use AlexFigures\JsonApi\Filter\Ast\Comparison;
use AlexFigures\JsonApi\Filter\Ast\Group;
use AlexFigures\JsonApi\Filter\Ast\NullCheck;
use AlexFigures\JsonApi\Http\Error\ErrorBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use AlexFigures\JsonApi\Http\Request\FilteringWhitelist;
use AlexFigures\JsonApi\Resource\Attribute\Attribute;
use AlexFigures\JsonApi\Resource\Attribute\FilterableField;
use AlexFigures\JsonApi\Resource\Attribute\FilterableFields;
use AlexFigures\JsonApi\Resource\Attribute\Id;
use AlexFigures\JsonApi\Resource\Attribute\JsonApiResource;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;

final class FilteringWhitelistSecurityTest extends TestCase
{
    public function testNullCheckBypassAttackIsBlocked(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $errors = new ErrorMapper(new ErrorBuilder(true));
        $whitelist = new FilteringWhitelist($registry, $errors);

        // Setup: Resource only allows 'title' field with 'eq' operator
        $filterableFields = new FilterableFields([
            new FilterableField('title', ['eq'])
        ]);

        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleFixture::class,
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields
        );

        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        // Attack: Try to use 'secret' field with 'isnull' operator via NullCheck node
        $maliciousNode = new NullCheck('secret', true);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter field not allowed.');

        $whitelist->validate('articles', $maliciousNode);
    }

    public function testBetweenBypassAttackIsBlocked(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $errors = new ErrorMapper(new ErrorBuilder(true));
        $whitelist = new FilteringWhitelist($registry, $errors);

        $filterableFields = new FilterableFields([
            new FilterableField('title', ['eq'])
        ]);

        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleFixture::class,
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields
        );

        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        $maliciousNode = new Between('secret', 1, 100);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter field not allowed.');

        $whitelist->validate('articles', $maliciousNode);
    }

    public function testGroupBypassAttackIsBlocked(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $errors = new ErrorMapper(new ErrorBuilder(true));
        $whitelist = new FilteringWhitelist($registry, $errors);

        $filterableFields = new FilterableFields([
            new FilterableField('title', ['eq'])
        ]);

        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleFixture::class,
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields
        );

        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        $maliciousComparison = new Comparison('secret', 'eq', ['confidential']);
        $maliciousNode = new Group($maliciousComparison);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter field not allowed.');

        $whitelist->validate('articles', $maliciousNode);
    }

    public function testLegitimateNullCheckIsAllowed(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $errors = new ErrorMapper(new ErrorBuilder(true));
        $whitelist = new FilteringWhitelist($registry, $errors);

        $filterableFields = new FilterableFields([
            new FilterableField('publishedAt', ['null', 'nnull'])
        ]);

        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleFixture::class,
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields
        );

        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        $legitimateNode = new NullCheck('publishedAt', true);

        // Should not throw any exception
        $whitelist->validate('articles', $legitimateNode);
        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    public function testLegitimateGroupIsAllowed(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $errors = new ErrorMapper(new ErrorBuilder(true));
        $whitelist = new FilteringWhitelist($registry, $errors);

        $filterableFields = new FilterableFields([
            new FilterableField('title', ['eq'])
        ]);

        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleFixture::class,
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields
        );

        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        $legitimateComparison = new Comparison('title', 'eq', ['Test Article']);
        $legitimateNode = new Group($legitimateComparison);

        // Should not throw any exception
        $whitelist->validate('articles', $legitimateNode);
        $this->assertTrue(true); // Test passes if no exception is thrown
    }
}

#[JsonApiResource(type: 'articles')]
final class ArticleFixture
{
    #[Id]
    #[Attribute]
    public string $id;

    #[Attribute]
    public string $title;

    #[Attribute]
    public ?string $status = null;
}
