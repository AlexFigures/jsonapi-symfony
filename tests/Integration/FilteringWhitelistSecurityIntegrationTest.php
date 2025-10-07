<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Filter\Parser\FilterParser;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Request\FilteringWhitelist;
use JsonApi\Symfony\Resource\Attribute\FilterableField;
use JsonApi\Symfony\Resource\Attribute\FilterableFields;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration test demonstrating the security fix for FilteringWhitelist.
 * 
 * This test simulates real-world attack scenarios where malicious clients
 * try to bypass the FilterableFields whitelist using various AST node types
 * that were previously unvalidated.
 */
final class FilteringWhitelistSecurityIntegrationTest extends TestCase
{
    private FilterParser $parser;
    private FilteringWhitelist $whitelist;
    private ResourceRegistryInterface $registry;

    protected function setUp(): void
    {
        $this->parser = new FilterParser();
        $this->registry = $this->createMock(ResourceRegistryInterface::class);
        $errors = new ErrorMapper(new ErrorBuilder(true));
        $this->whitelist = new FilteringWhitelist($this->registry, $errors);
    }

    public function testRealWorldBypassAttackScenarios(): void
    {
        // Setup: A blog API that only allows filtering by 'title' and 'status'
        $filterableFields = new FilterableFields([
            new FilterableField('title', ['eq', 'like']),
            new FilterableField('status', ['eq', 'in'])
        ]);
        
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        // Attack 1: Try to access secret 'adminNotes' field using isnull
        $maliciousFilter1 = ['adminNotes' => ['isnull' => true]];
        $ast1 = $this->parser->parse($maliciousFilter1);
        
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter field not allowed.');
        
        $this->whitelist->validate('articles', $ast1);
    }

    public function testComplexNestedBypassAttack(): void
    {
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

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        // Attack: Hide malicious filter in complex nested structure
        $maliciousFilter = [
            'and' => [
                ['title' => 'Legitimate Title'],  // This is allowed
                ['secret' => ['isnull' => true]]  // This should be blocked
            ]
        ];
        
        $ast = $this->parser->parse($maliciousFilter);
        
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter field not allowed.');
        
        $this->whitelist->validate('articles', $ast);
    }

    public function testBetweenOperatorBypassAttack(): void
    {
        $filterableFields = new FilterableFields([
            new FilterableField('publishedAt', ['eq', 'gt', 'lt'])  // between not allowed
        ]);
        
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        // Attack: Try to use disallowed 'between' operator
        $maliciousFilter = [
            'publishedAt' => [
                'between' => ['2024-01-01', '2024-12-31']
            ]
        ];
        
        $ast = $this->parser->parse($maliciousFilter);
        
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter operator not allowed.');
        
        $this->whitelist->validate('articles', $ast);
    }

    public function testLegitimateComplexFilterIsAllowed(): void
    {
        $filterableFields = new FilterableFields([
            new FilterableField('title', ['eq', 'like']),
            new FilterableField('status', ['eq', 'in']),
            new FilterableField('publishedAt', ['null', 'nnull', 'between'])
        ]);
        
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\\Entity\\Article',
            attributes: [],
            relationships: [],
            filterableFields: $filterableFields
        );

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        // Legitimate complex filter using allowed fields and operators
        $legitimateFilter = [
            'and' => [
                ['title' => ['like' => '%security%']],
                ['status' => ['in' => ['published', 'featured']]],
                ['publishedAt' => ['isnull' => false]]
            ]
        ];
        
        $ast = $this->parser->parse($legitimateFilter);
        
        // Should not throw any exception
        $this->whitelist->validate('articles', $ast);
        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    public function testGroupedExpressionBypassAttack(): void
    {
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

        $this->registry->method('hasType')->with('articles')->willReturn(true);
        $this->registry->method('getByType')->with('articles')->willReturn($metadata);

        // Attack: Try to use grouped expressions to hide malicious filter
        // Note: This simulates what would happen if Group nodes were created
        // The current parser doesn't create Group nodes, but the validation
        // should handle them if they were created by other means
        
        $maliciousFilter = ['secret' => 'confidential'];
        $ast = $this->parser->parse($maliciousFilter);
        
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Filter field not allowed.');
        
        $this->whitelist->validate('articles', $ast);
    }
}
