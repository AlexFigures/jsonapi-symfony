<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Filter\Ast\Comparison;
use JsonApi\Symfony\Filter\Ast\Conjunction;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Request\FilteringWhitelist;
use JsonApi\Symfony\Resource\Attribute\FilterableField;
use JsonApi\Symfony\Resource\Attribute\FilterableFields;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;

final class FilterableFieldsIntegrationTest extends TestCase
{
    private ResourceRegistryInterface $registry;
    private FilteringWhitelist $filteringWhitelist;

    protected function setUp(): void
    {
        // Create a mock resource with FilterableFields
        $filterableFields = new FilterableFields([
            new FilterableField('title', operators: ['eq', 'like']),
            new FilterableField('status', operators: ['eq', 'in']),
            new FilterableField('viewCount', operators: ['gte', 'lte']),
            'authorId', // All operators allowed
        ]);

        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields
        );

        $this->registry = $this->createMock(ResourceRegistryInterface::class);
        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        $errorMapper = new ErrorMapper(new ErrorBuilder(true));
        $this->filteringWhitelist = new FilteringWhitelist($this->registry, $errorMapper);
    }

    public function testAllowedFieldAndOperator(): void
    {
        $comparison = new Comparison('title', 'eq', ['Test']);
        
        // Should not throw exception
        $this->filteringWhitelist->validate('articles', $comparison);
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function testAllowedFieldWithAllOperators(): void
    {
        $comparison = new Comparison('authorId', 'gt', ['5']);
        
        // Should not throw exception (authorId allows all operators)
        $this->filteringWhitelist->validate('articles', $comparison);
        $this->assertTrue(true);
    }

    public function testDisallowedOperator(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter operator not allowed.');

        $comparison = new Comparison('title', 'gt', ['Test']);
        $this->filteringWhitelist->validate('articles', $comparison);
    }

    public function testDisallowedField(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter field not allowed.');

        $comparison = new Comparison('description', 'eq', ['Test']);
        $this->filteringWhitelist->validate('articles', $comparison);
    }

    public function testComplexFilterWithConjunction(): void
    {
        $comparison1 = new Comparison('title', 'like', ['%test%']);
        $comparison2 = new Comparison('status', 'eq', ['published']);
        $comparison3 = new Comparison('viewCount', 'gte', ['100']);
        
        $conjunction = new Conjunction([$comparison1, $comparison2, $comparison3]);
        
        // Should not throw exception
        $this->filteringWhitelist->validate('articles', $conjunction);
        $this->assertTrue(true);
    }

    public function testComplexFilterWithInvalidOperator(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter operator not allowed.');

        $comparison1 = new Comparison('title', 'gt', ['Test']); // Invalid operator
        $comparison2 = new Comparison('status', 'eq', ['published']);

        $conjunction = new Conjunction([$comparison1, $comparison2]);
        $this->filteringWhitelist->validate('articles', $conjunction);
    }

    public function testNoFilterableFieldsConfigured(): void
    {
        // Create metadata without FilterableFields
        $metadata = new ResourceMetadata(
            type: 'users',
            class: 'App\\Entity\\User',
            attributes: [],
            relationships: [],
            filterableFields: null
        );

        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('hasType')->with('users')->willReturn(true);
        $registry->method('getByType')->with('users')->willReturn($metadata);

        $errorMapper = new ErrorMapper(new ErrorBuilder(true));
        $filteringWhitelist = new FilteringWhitelist($registry, $errorMapper);

        // Should reject filtering when no FilterableFields configured
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filtering not allowed.');

        $comparison = new Comparison('anyField', 'anyOperator', ['value']);
        $filteringWhitelist->validate('users', $comparison);
    }

    public function testUnknownResourceType(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $registry->method('hasType')->with('unknown')->willReturn(false);

        $errorMapper = new ErrorMapper(new ErrorBuilder(true));
        $filteringWhitelist = new FilteringWhitelist($registry, $errorMapper);

        // Should allow any filter for unknown resource types
        $comparison = new Comparison('anyField', 'anyOperator', ['value']);
        $filteringWhitelist->validate('unknown', $comparison);
        $this->assertTrue(true);
    }

    public function testNullFilter(): void
    {
        // Should handle null filter gracefully
        $this->filteringWhitelist->validate('articles', null);
        $this->assertTrue(true);
    }
}
