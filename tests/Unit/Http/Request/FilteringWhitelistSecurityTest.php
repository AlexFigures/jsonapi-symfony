<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Request;

use JsonApi\Symfony\Filter\Ast\Between;
use JsonApi\Symfony\Filter\Ast\Comparison;
use JsonApi\Symfony\Filter\Ast\Group;
use JsonApi\Symfony\Filter\Ast\NullCheck;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Request\FilteringWhitelist;
use JsonApi\Symfony\Resource\Attribute\FilterableField;
use JsonApi\Symfony\Resource\Attribute\FilterableFields;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
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
            class: 'App\\Entity\\Article',
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
            class: 'App\\Entity\\Article',
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
            class: 'App\\Entity\\Article',
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
            class: 'App\\Entity\\Article',
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
            class: 'App\\Entity\\Article',
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
