<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Request;

use JsonApi\Symfony\Filter\Ast\Comparison;
use JsonApi\Symfony\Filter\Ast\Conjunction;
use JsonApi\Symfony\Filter\Ast\Disjunction;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Request\FilteringWhitelist;
use JsonApi\Symfony\Resource\Attribute\FilterableField;
use JsonApi\Symfony\Resource\Attribute\FilterableFields;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;

final class FilteringWhitelistTest extends TestCase
{
    private ResourceRegistryInterface $registry;
    private ErrorMapper $errorMapper;
    private FilteringWhitelist $whitelist;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ResourceRegistryInterface::class);
        $errorBuilder = new ErrorBuilder(true);
        $this->errorMapper = new ErrorMapper($errorBuilder);
        $this->whitelist = new FilteringWhitelist($this->registry, $this->errorMapper);
    }

    public function testAllowedForReturnsEmptyArrayWhenTypeNotInRegistry(): void
    {
        $this->registry->method('hasType')->with('unknown')->willReturn(false);

        self::assertSame([], $this->whitelist->allowedFor('unknown'));
    }

    public function testAllowedForReturnsEmptyArrayWhenNoFilterableFields(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            filterableFields: null,
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        self::assertSame([], $this->whitelist->allowedFor('articles'));
    }

    public function testAllowedForReturnsFieldsFromAttribute(): void
    {
        $filterableFields = new FilterableFields(['title', 'status']);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        self::assertSame(['title', 'status'], $this->whitelist->allowedFor('articles'));
    }

    public function testIsFieldAllowed(): void
    {
        $filterableFields = new FilterableFields(['title', 'status']);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        self::assertTrue($this->whitelist->isFieldAllowed('articles', 'title'));
        self::assertTrue($this->whitelist->isFieldAllowed('articles', 'status'));
        self::assertFalse($this->whitelist->isFieldAllowed('articles', 'content'));
    }

    public function testIsOperatorAllowed(): void
    {
        $titleField = new FilterableField('title', ['eq', 'like']);
        $filterableFields = new FilterableFields([$titleField, 'status']);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        // Title field has restricted operators
        self::assertTrue($this->whitelist->isOperatorAllowed('articles', 'title', 'eq'));
        self::assertTrue($this->whitelist->isOperatorAllowed('articles', 'title', 'like'));
        self::assertFalse($this->whitelist->isOperatorAllowed('articles', 'title', 'gt'));

        // Status field has all operators by default
        self::assertTrue($this->whitelist->isOperatorAllowed('articles', 'status', 'eq'));
        self::assertTrue($this->whitelist->isOperatorAllowed('articles', 'status', 'gt'));
    }

    public function testValidateWithNullNode(): void
    {
        // Should not throw any exception
        $this->whitelist->validate('articles', null);
        $this->addToAssertionCount(1);
    }

    public function testValidateThrowsWhenNoFilterableFieldsDefined(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            filterableFields: null,
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        $comparison = new Comparison('title', 'eq', ['test']);

        $this->expectException(BadRequestException::class);
        $this->whitelist->validate('articles', $comparison);
    }

    public function testValidateComparisonSuccess(): void
    {
        $filterableFields = new FilterableFields(['title']);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        $comparison = new Comparison('title', 'eq', ['test']);

        // Should not throw any exception
        $this->whitelist->validate('articles', $comparison);
        $this->addToAssertionCount(1);
    }

    public function testValidateComparisonThrowsForDisallowedField(): void
    {
        $filterableFields = new FilterableFields(['title']);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        $comparison = new Comparison('content', 'eq', ['test']);

        $this->expectException(BadRequestException::class);
        $this->whitelist->validate('articles', $comparison);
    }

    public function testValidateComparisonThrowsForDisallowedOperator(): void
    {
        $titleField = new FilterableField('title', ['eq']);
        $filterableFields = new FilterableFields([$titleField]);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        $comparison = new Comparison('title', 'like', ['test']);

        $this->expectException(BadRequestException::class);
        $this->whitelist->validate('articles', $comparison);
    }

    public function testValidateConjunctionSuccess(): void
    {
        $filterableFields = new FilterableFields(['title', 'status']);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        $conjunction = new Conjunction([
            new Comparison('title', 'eq', ['test']),
            new Comparison('status', 'eq', ['published']),
        ]);

        // Should not throw any exception
        $this->whitelist->validate('articles', $conjunction);
        $this->addToAssertionCount(1);
    }

    public function testValidateDisjunctionSuccess(): void
    {
        $filterableFields = new FilterableFields(['title', 'content']);
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields,
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        $disjunction = new Disjunction([
            new Comparison('title', 'like', ['%test%']),
            new Comparison('content', 'like', ['%test%']),
        ]);

        // Should not throw any exception
        $this->whitelist->validate('articles', $disjunction);
        $this->addToAssertionCount(1);
    }
}
