<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Link;

use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Query\Pagination;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(LinkGenerator::class)]
final class LinkGeneratorTest extends TestCase
{
    public function testTopLevelSelfReturnsRequestUri(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $linkGenerator = new LinkGenerator($urlGenerator);

        $request = Request::create('https://example.com/api/articles?page[number]=2');

        $result = $linkGenerator->topLevelSelf($request);

        // URL encoding is expected (page[number] becomes page%5Bnumber%5D)
        $this->assertSame('https://example.com/api/articles?page%5Bnumber%5D=2', $result);
    }

    public function testResourceSelfGeneratesCorrectRoute(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                'jsonapi.articles.show',
                ['id' => '123'],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.com/api/articles/123');

        $linkGenerator = new LinkGenerator($urlGenerator);
        
        $result = $linkGenerator->resourceSelf('articles', '123');
        
        $this->assertSame('https://example.com/api/articles/123', $result);
    }

    public function testRelationshipSelfGeneratesCorrectRoute(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                'jsonapi.articles.relationships.author.show',
                ['id' => '123'],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.com/api/articles/123/relationships/author');

        $linkGenerator = new LinkGenerator($urlGenerator);
        
        $result = $linkGenerator->relationshipSelf('articles', '123', 'author');
        
        $this->assertSame('https://example.com/api/articles/123/relationships/author', $result);
    }

    public function testRelationshipRelatedGeneratesCorrectRoute(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                'jsonapi.articles.related.author',
                ['id' => '123'],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.com/api/articles/123/author');

        $linkGenerator = new LinkGenerator($urlGenerator);
        
        $result = $linkGenerator->relationshipRelated('articles', '123', 'author');
        
        $this->assertSame('https://example.com/api/articles/123/author', $result);
    }

    public function testCollectionPaginationGeneratesAllLinks(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(function (string $route, array $params) {
                $pageNumber = $params['page']['number'] ?? 1;
                return "https://example.com/api/articles?page[number]={$pageNumber}";
            });

        $linkGenerator = new LinkGenerator($urlGenerator);
        
        $request = Request::create('https://example.com/api/articles?page[number]=2');
        $pagination = new Pagination(number: 2, size: 10);
        
        $result = $linkGenerator->collectionPagination('articles', $pagination, 50, $request);
        
        $this->assertArrayHasKey('first', $result);
        $this->assertArrayHasKey('last', $result);
        $this->assertArrayHasKey('prev', $result);
        $this->assertArrayHasKey('next', $result);
        
        $this->assertStringContainsString('page[number]=1', $result['first']);
        $this->assertStringContainsString('page[number]=5', $result['last']);
        $this->assertStringContainsString('page[number]=1', $result['prev']);
        $this->assertStringContainsString('page[number]=3', $result['next']);
    }

    public function testCollectionPaginationFirstPageNoPrevLink(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(function (string $route, array $params) {
                $pageNumber = $params['page']['number'] ?? 1;
                return "https://example.com/api/articles?page[number]={$pageNumber}";
            });

        $linkGenerator = new LinkGenerator($urlGenerator);
        
        $request = Request::create('https://example.com/api/articles?page[number]=1');
        $pagination = new Pagination(number: 1, size: 10);
        
        $result = $linkGenerator->collectionPagination('articles', $pagination, 50, $request);
        
        $this->assertArrayHasKey('first', $result);
        $this->assertArrayHasKey('last', $result);
        $this->assertArrayNotHasKey('prev', $result);
        $this->assertArrayHasKey('next', $result);
    }

    public function testCollectionPaginationLastPageNoNextLink(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(function (string $route, array $params) {
                $pageNumber = $params['page']['number'] ?? 1;
                return "https://example.com/api/articles?page[number]={$pageNumber}";
            });

        $linkGenerator = new LinkGenerator($urlGenerator);
        
        $request = Request::create('https://example.com/api/articles?page[number]=5');
        $pagination = new Pagination(number: 5, size: 10);
        
        $result = $linkGenerator->collectionPagination('articles', $pagination, 50, $request);
        
        $this->assertArrayHasKey('first', $result);
        $this->assertArrayHasKey('last', $result);
        $this->assertArrayHasKey('prev', $result);
        $this->assertArrayNotHasKey('next', $result);
    }

    public function testCollectionPaginationUsesCorrectRouteName(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->atLeastOnce())
            ->method('generate')
            ->with(
                $this->stringStartsWith('jsonapi.articles.index'),
                $this->anything(),
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.com/api/articles');

        $linkGenerator = new LinkGenerator($urlGenerator);
        
        $request = Request::create('https://example.com/api/articles');
        $pagination = new Pagination(number: 1, size: 10);
        
        $linkGenerator->collectionPagination('articles', $pagination, 10, $request);
    }
}

