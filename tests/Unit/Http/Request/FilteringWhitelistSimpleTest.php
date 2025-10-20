<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Http\Request;

use AlexFigures\Symfony\Filter\Ast\Comparison;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Resource\Attribute\FilterableField;
use AlexFigures\Symfony\Resource\Attribute\FilterableFields;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;

final class FilteringWhitelistSimpleTest extends TestCase
{
    public function testBasicFunctionality(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);
        $whitelist = new FilteringWhitelist($registry, $errorMapper);

        // Test with no type in registry
        $registry->method('hasType')->willReturnCallback(function ($type) {
            return $type === 'articles';
        });

        // Test with filterable fields
        $filterableFields = new FilterableFields(['title', 'status']);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $registry->method('getByType')->with('articles')->willReturn($metadata);

        self::assertSame([], $whitelist->allowedFor('unknown'));

        self::assertSame(['title', 'status'], $whitelist->allowedFor('articles'));
        self::assertTrue($whitelist->isFieldAllowed('articles', 'title'));
        self::assertFalse($whitelist->isFieldAllowed('articles', 'content'));
    }

    public function testOperatorRestrictions(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);
        $whitelist = new FilteringWhitelist($registry, $errorMapper);

        // Create fields with restricted operators
        $titleField = new FilterableField('title', ['eq', 'like']);
        $statusField = new FilterableField('status', ['eq', 'in']);
        $filterableFields = new FilterableFields([$titleField, $statusField]);

        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        // Test allowed operators
        self::assertTrue($whitelist->isOperatorAllowed('articles', 'title', 'eq'));
        self::assertTrue($whitelist->isOperatorAllowed('articles', 'title', 'like'));
        self::assertFalse($whitelist->isOperatorAllowed('articles', 'title', 'gt'));

        self::assertTrue($whitelist->isOperatorAllowed('articles', 'status', 'eq'));
        self::assertTrue($whitelist->isOperatorAllowed('articles', 'status', 'in'));
        self::assertFalse($whitelist->isOperatorAllowed('articles', 'status', 'like'));
    }

    public function testValidationSuccess(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);
        $whitelist = new FilteringWhitelist($registry, $errorMapper);

        $filterableFields = new FilterableFields(['title']);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        $comparison = new Comparison('title', 'eq', ['test']);

        // Should not throw any exception
        $whitelist->validate('articles', $comparison);
        $this->addToAssertionCount(1);
    }

    public function testValidationFailsForDisallowedField(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);
        $whitelist = new FilteringWhitelist($registry, $errorMapper);

        $filterableFields = new FilterableFields(['title']);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        $comparison = new Comparison('content', 'eq', ['test']);

        $this->expectException(BadRequestException::class);
        $whitelist->validate('articles', $comparison);
    }

    public function testValidationFailsForDisallowedOperator(): void
    {
        $registry = $this->createMock(ResourceRegistryInterface::class);
        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);
        $whitelist = new FilteringWhitelist($registry, $errorMapper);

        $titleField = new FilterableField('title', ['eq']); // Only 'eq' allowed
        $filterableFields = new FilterableFields([$titleField]);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\Symfony\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $registry->method('hasType')->with('articles')->willReturn(true);
        $registry->method('getByType')->with('articles')->willReturn($metadata);

        $comparison = new Comparison('title', 'like', ['test']); // 'like' not allowed

        $this->expectException(BadRequestException::class);
        $whitelist->validate('articles', $comparison);
    }
}
