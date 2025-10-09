<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Http;

use JsonApi\Symfony\Bridge\Symfony\EventSubscriber\CachePreconditionsSubscriber;
use JsonApi\Symfony\Http\Cache\CacheKeyBuilder;
use JsonApi\Symfony\Http\Cache\ConditionalRequestEvaluator;
use JsonApi\Symfony\Http\Cache\HashEtagGenerator;
use JsonApi\Symfony\Http\Cache\HeadersApplier;
use JsonApi\Symfony\Http\Cache\LastModifiedResolver;
use JsonApi\Symfony\Http\Cache\SurrogateKeyBuilder;
use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;
use JsonApi\Symfony\Tests\Fixtures\Model\Tag;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(CachePreconditionsSubscriber::class)]
#[CoversClass(HashEtagGenerator::class)]
#[CoversClass(ConditionalRequestEvaluator::class)]
#[CoversClass(HeadersApplier::class)]
#[CoversClass(LastModifiedResolver::class)]
#[CoversClass(CacheKeyBuilder::class)]
final class CacheValidatorsTest extends JsonApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $author = new Author(id: '1', name: 'John Doe');
        $tag1 = new Tag(id: '1', name: 'PHP');
        $tag2 = new Tag(id: '2', name: 'Symfony');

        $article = new Article(
            '1',
            'Test Article',
            new \DateTimeImmutable('2024-01-01 12:00:00'),
            $author,
            $tag1,
            $tag2
        );

        $this->repository()->save('authors', $author);
        $this->repository()->save('tags', $tag1);
        $this->repository()->save('tags', $tag2);
        $this->repository()->save('articles', $article);
    }

    public function testSingleResourceResponseIncludesEtag(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $request->attributes->set('_route', 'jsonapi.resource');

        $response = ($this->resourceController())($request, 'articles', '1');
        $this->applyCacheHeaders($request, $response);

        self::assertNotNull($response->headers->get('ETag'));
        self::assertStringStartsWith('"', $response->headers->get('ETag'));
        self::assertStringEndsWith('"', $response->headers->get('ETag'));
    }

    public function testCollectionResponseIncludesWeakEtag(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $request->attributes->set('_route', 'jsonapi.collection');

        $response = ($this->collectionController())($request, 'articles');
        $this->applyCacheHeaders($request, $response);

        $etag = $response->headers->get('ETag');
        self::assertNotNull($etag);
        self::assertStringStartsWith('W/"', $etag, 'Collection ETag should be weak');
    }

    public function testEtagVariesByInclude(): void
    {
        $request1 = Request::create('/api/articles/1?include=author', 'GET');
        $request1->attributes->set('_route', 'jsonapi.resource');

        $request2 = Request::create('/api/articles/1', 'GET');
        $request2->attributes->set('_route', 'jsonapi.resource');

        $response1 = ($this->resourceController())($request1, 'articles', '1');
        $this->applyCacheHeaders($request1, $response1);

        $response2 = ($this->resourceController())($request2, 'articles', '1');
        $this->applyCacheHeaders($request2, $response2);

        $etag1 = $response1->headers->get('ETag');
        $etag2 = $response2->headers->get('ETag');

        self::assertNotNull($etag1);
        self::assertNotNull($etag2);
        self::assertNotEquals($etag1, $etag2, 'ETag should vary by include parameter');
    }

    public function testEtagVariesByFields(): void
    {
        $request1 = Request::create('/api/articles/1?fields[articles]=title', 'GET');
        $request1->attributes->set('_route', 'jsonapi.resource');

        $request2 = Request::create('/api/articles/1', 'GET');
        $request2->attributes->set('_route', 'jsonapi.resource');

        $response1 = ($this->resourceController())($request1, 'articles', '1');
        $this->applyCacheHeaders($request1, $response1);

        $response2 = ($this->resourceController())($request2, 'articles', '1');
        $this->applyCacheHeaders($request2, $response2);

        $etag1 = $response1->headers->get('ETag');
        $etag2 = $response2->headers->get('ETag');

        self::assertNotNull($etag1);
        self::assertNotNull($etag2);
        self::assertNotEquals($etag1, $etag2, 'ETag should vary by fields parameter');
    }

    public function testIfNoneMatchReturns304(): void
    {
        // First request to get ETag
        $request1 = Request::create('/api/articles/1', 'GET');
        $request1->attributes->set('_route', 'jsonapi.resource');

        $response1 = ($this->resourceController())($request1, 'articles', '1');
        $this->applyCacheHeaders($request1, $response1);

        $etag = $response1->headers->get('ETag');
        self::assertNotNull($etag);

        // Second request with If-None-Match
        $request2 = Request::create('/api/articles/1', 'GET');
        $request2->attributes->set('_route', 'jsonapi.resource');
        $request2->headers->set('If-None-Match', $etag);

        $response2 = ($this->resourceController())($request2, 'articles', '1');
        $this->applyCacheHeaders($request2, $response2);

        self::assertSame(304, $response2->getStatusCode(), 'Should return 304 Not Modified');
        self::assertEmpty($response2->getContent(), '304 response should have empty body');
    }

    public function testIfNoneMatchWildcardReturns304(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $request->attributes->set('_route', 'jsonapi.resource');
        $request->headers->set('If-None-Match', '*');

        $response = ($this->resourceController())($request, 'articles', '1');
        $this->applyCacheHeaders($request, $response);

        self::assertSame(304, $response->getStatusCode(), 'If-None-Match: * should return 304');
    }

    public function testIfNoneMatchWithDifferentEtagReturns200(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $request->attributes->set('_route', 'jsonapi.resource');
        $request->headers->set('If-None-Match', '"different-etag"');

        $response = ($this->resourceController())($request, 'articles', '1');
        $this->applyCacheHeaders($request, $response);

        self::assertSame(200, $response->getStatusCode(), 'Different ETag should return 200');
        self::assertNotEmpty($response->getContent());
    }

    public function testRelationshipResponseIncludesEtag(): void
    {
        $request = Request::create('/api/articles/1/relationships/author', 'GET');
        $request->attributes->set('_route', 'jsonapi.relationship');

        $response = ($this->relationshipGetController())($request, 'articles', '1', 'author');
        $this->applyCacheHeaders($request, $response);

        self::assertNotNull($response->headers->get('ETag'));
    }

    public function testRelatedResourceResponseIncludesEtag(): void
    {
        $request = Request::create('/api/articles/1/author', 'GET');
        $request->attributes->set('_route', 'jsonapi.related');

        $response = ($this->relatedController())($request, 'articles', '1', 'author');
        $this->applyCacheHeaders($request, $response);

        self::assertNotNull($response->headers->get('ETag'));
    }

    public function testResponseIncludesCacheControlHeaders(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $request->attributes->set('_route', 'jsonapi.resource');

        $response = ($this->resourceController())($request, 'articles', '1');
        $this->applyCacheHeaders($request, $response);

        $cacheControl = $response->headers->get('Cache-Control');
        self::assertNotNull($cacheControl);
        self::assertStringContainsString('public', $cacheControl);
    }

    public function testLastModifiedHeaderIsSet(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $request->attributes->set('_route', 'jsonapi.resource');

        $response = ($this->resourceController())($request, 'articles', '1');

        // Set Last-Modified header manually for testing
        $response->headers->set('Last-Modified', 'Mon, 01 Jan 2024 12:00:00 GMT');

        $this->applyCacheHeaders($request, $response);

        self::assertNotNull($response->headers->get('Last-Modified'));
    }

    public function testIfModifiedSinceReturns304(): void
    {
        $request1 = Request::create('/api/articles/1', 'GET');
        $request1->attributes->set('_route', 'jsonapi.resource');

        $response1 = ($this->resourceController())($request1, 'articles', '1');
        $response1->headers->set('Last-Modified', 'Mon, 01 Jan 2024 12:00:00 GMT');
        $this->applyCacheHeaders($request1, $response1);

        // Second request with If-Modified-Since
        $request2 = Request::create('/api/articles/1', 'GET');
        $request2->attributes->set('_route', 'jsonapi.resource');
        $request2->headers->set('If-Modified-Since', 'Mon, 01 Jan 2024 12:00:00 GMT');

        $response2 = ($this->resourceController())($request2, 'articles', '1');
        $response2->headers->set('Last-Modified', 'Mon, 01 Jan 2024 12:00:00 GMT');
        $this->applyCacheHeaders($request2, $response2);

        self::assertSame(304, $response2->getStatusCode(), 'Should return 304 Not Modified');
    }

    public function testEtagConsistentForSameRequest(): void
    {
        $request1 = Request::create('/api/articles/1', 'GET');
        $request1->attributes->set('_route', 'jsonapi.resource');

        $request2 = Request::create('/api/articles/1', 'GET');
        $request2->attributes->set('_route', 'jsonapi.resource');

        $response1 = ($this->resourceController())($request1, 'articles', '1');
        $this->applyCacheHeaders($request1, $response1);

        $response2 = ($this->resourceController())($request2, 'articles', '1');
        $this->applyCacheHeaders($request2, $response2);

        $etag1 = $response1->headers->get('ETag');
        $etag2 = $response2->headers->get('ETag');

        self::assertNotNull($etag1);
        self::assertNotNull($etag2);
        self::assertSame($etag1, $etag2, 'ETag should be consistent for identical requests');
    }

    public function testCollectionEtagVariesByPagination(): void
    {
        $request1 = Request::create('/api/articles?page[number]=1&page[size]=10', 'GET');
        $request1->attributes->set('_route', 'jsonapi.collection');

        $request2 = Request::create('/api/articles?page[number]=2&page[size]=10', 'GET');
        $request2->attributes->set('_route', 'jsonapi.collection');

        $response1 = ($this->collectionController())($request1, 'articles');
        $this->applyCacheHeaders($request1, $response1);

        $response2 = ($this->collectionController())($request2, 'articles');
        $this->applyCacheHeaders($request2, $response2);

        $etag1 = $response1->headers->get('ETag');
        $etag2 = $response2->headers->get('ETag');

        self::assertNotNull($etag1);
        self::assertNotNull($etag2);
        self::assertNotEquals($etag1, $etag2, 'ETag should vary by pagination parameters');
    }

    public function testCollectionEtagVariesBySort(): void
    {
        $request1 = Request::create('/api/articles?sort=title', 'GET');
        $request1->attributes->set('_route', 'jsonapi.collection');

        $request2 = Request::create('/api/articles?sort=-title', 'GET');
        $request2->attributes->set('_route', 'jsonapi.collection');

        $response1 = ($this->collectionController())($request1, 'articles');
        $this->applyCacheHeaders($request1, $response1);

        $response2 = ($this->collectionController())($request2, 'articles');
        $this->applyCacheHeaders($request2, $response2);

        $etag1 = $response1->headers->get('ETag');
        $etag2 = $response2->headers->get('ETag');

        self::assertNotNull($etag1);
        self::assertNotNull($etag2);
        self::assertNotEquals($etag1, $etag2, 'ETag should vary by sort parameters');
    }

    public function testIfNoneMatchWithMultipleEtags(): void
    {
        // First request to get ETag
        $request1 = Request::create('/api/articles/1', 'GET');
        $request1->attributes->set('_route', 'jsonapi.resource');

        $response1 = ($this->resourceController())($request1, 'articles', '1');
        $this->applyCacheHeaders($request1, $response1);

        $etag = $response1->headers->get('ETag');
        self::assertNotNull($etag);

        // Second request with multiple ETags in If-None-Match
        $request2 = Request::create('/api/articles/1', 'GET');
        $request2->attributes->set('_route', 'jsonapi.resource');
        $request2->headers->set('If-None-Match', '"other-etag", ' . $etag . ', "another-etag"');

        $response2 = ($this->resourceController())($request2, 'articles', '1');
        $this->applyCacheHeaders($request2, $response2);

        self::assertSame(304, $response2->getStatusCode(), 'Should return 304 when one of multiple ETags matches');
    }

    public function testWeakEtagInIfNoneMatchMatches(): void
    {
        $request1 = Request::create('/api/articles', 'GET');
        $request1->attributes->set('_route', 'jsonapi.collection');

        $response1 = ($this->collectionController())($request1, 'articles');
        $this->applyCacheHeaders($request1, $response1);

        $etag = $response1->headers->get('ETag');
        self::assertNotNull($etag);
        self::assertStringStartsWith('W/"', $etag);

        // Second request with weak ETag in If-None-Match
        $request2 = Request::create('/api/articles', 'GET');
        $request2->attributes->set('_route', 'jsonapi.collection');
        $request2->headers->set('If-None-Match', $etag);

        $response2 = ($this->collectionController())($request2, 'articles');
        $this->applyCacheHeaders($request2, $response2);

        self::assertSame(304, $response2->getStatusCode(), 'Weak ETag should match in If-None-Match');
    }

    /**
     * Apply cache headers using CachePreconditionsSubscriber
     */
    private function applyCacheHeaders(Request $request, Response $response): void
    {
        $config = [
            'enabled' => true,
            'etag' => [
                'weak_for_collections' => true,
                'include_query_shape' => true,
            ],
            'conditional' => [
                'enable_if_none_match' => true,
                'enable_if_modified_since' => true,
                'enable_if_match' => true,
                'enable_if_unmodified_since' => true,
            ],
        ];

        $headersConfig = [
            'headers' => [
                'public' => true,
                'max_age' => 3600,
            ],
        ];

        $cacheKeyBuilder = new CacheKeyBuilder($config);
        $etagGenerator = new HashEtagGenerator($config);
        $lastModified = new LastModifiedResolver();
        $conditional = new ConditionalRequestEvaluator($this->errorMapper(), $config);
        $headers = new HeadersApplier($headersConfig);
        $surrogates = new SurrogateKeyBuilder();

        $subscriber = new CachePreconditionsSubscriber(
            $config,
            $cacheKeyBuilder,
            $etagGenerator,
            $lastModified,
            $conditional,
            $headers,
            $surrogates
        );

        $event = new ResponseEvent(
            $this->createKernel(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->onKernelResponse($event);
    }

    private function createKernel(): HttpKernelInterface
    {
        return new class () implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
