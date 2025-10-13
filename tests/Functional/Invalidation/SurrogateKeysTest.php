<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Invalidation;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\CachePreconditionsSubscriber;
use AlexFigures\Symfony\Http\Cache\CacheKeyBuilder;
use AlexFigures\Symfony\Http\Cache\ConditionalRequestEvaluator;
use AlexFigures\Symfony\Http\Cache\HashEtagGenerator;
use AlexFigures\Symfony\Http\Cache\HeadersApplier;
use AlexFigures\Symfony\Http\Cache\LastModifiedResolver;
use AlexFigures\Symfony\Http\Cache\SurrogateKeyBuilder;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * GAP-011: Surrogate Keys & Invalidation
 *
 * Tests that Surrogate-Key headers are properly generated and included in responses:
 * - Resource responses include surrogate keys
 * - Collection responses include surrogate keys
 * - Relationship responses include surrogate keys
 * - Surrogate key format is correct (type, type:id, type:id:rel)
 */
final class SurrogateKeysTest extends JsonApiTestCase
{
    public function testResourceResponseIncludesSurrogateKeys(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $request->attributes->set('_route', 'jsonapi.resource');
        $request->attributes->set('type', 'articles');
        $request->attributes->set('id', '1');

        $response = $this->resourceController()($request, 'articles', '1');

        // Apply cache headers using subscriber
        $this->applyCacheHeaders($request, $response);

        // Check that Surrogate-Key header is present
        self::assertTrue($response->headers->has('Surrogate-Key'), 'Response should have Surrogate-Key header');

        $surrogateKey = $response->headers->get('Surrogate-Key');
        self::assertNotNull($surrogateKey);

        // Should contain both collection key (articles) and resource key (articles:1)
        self::assertStringContainsString('articles', $surrogateKey);
        self::assertStringContainsString('articles:1', $surrogateKey);
    }

    public function testCollectionResponseIncludesSurrogateKeys(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $request->attributes->set('_route', 'jsonapi.collection');
        $request->attributes->set('type', 'articles');

        $response = $this->collectionController()($request, 'articles');

        // Apply cache headers using subscriber
        $this->applyCacheHeaders($request, $response);

        // Check that Surrogate-Key header is present
        self::assertTrue($response->headers->has('Surrogate-Key'), 'Response should have Surrogate-Key header');

        $surrogateKey = $response->headers->get('Surrogate-Key');
        self::assertNotNull($surrogateKey);

        // Should contain collection key (articles)
        self::assertStringContainsString('articles', $surrogateKey);
    }

    public function testRelationshipResponseIncludesSurrogateKeys(): void
    {
        $request = Request::create('/api/articles/1/relationships/author', 'GET');
        $request->attributes->set('_route', 'jsonapi.relationship.get');
        $request->attributes->set('type', 'articles');
        $request->attributes->set('id', '1');
        $request->attributes->set('relationship', 'author');

        $response = $this->relationshipGetController()($request, 'articles', '1', 'author');

        // Apply cache headers using subscriber
        $this->applyCacheHeaders($request, $response);

        // Check that Surrogate-Key header is present
        self::assertTrue($response->headers->has('Surrogate-Key'), 'Response should have Surrogate-Key header');

        $surrogateKey = $response->headers->get('Surrogate-Key');
        self::assertNotNull($surrogateKey);

        // Should contain collection key, resource key, and relationship key
        self::assertStringContainsString('articles', $surrogateKey);
        self::assertStringContainsString('articles:1', $surrogateKey);
        self::assertStringContainsString('articles:1:author', $surrogateKey);
    }

    public function testSurrogateKeyFormat(): void
    {
        $request = Request::create('/api/articles/1/relationships/author', 'GET');
        $request->attributes->set('_route', 'jsonapi.relationship.get');
        $request->attributes->set('type', 'articles');
        $request->attributes->set('id', '1');
        $request->attributes->set('relationship', 'author');

        $response = $this->relationshipGetController()($request, 'articles', '1', 'author');

        // Apply cache headers using subscriber
        $this->applyCacheHeaders($request, $response);

        $surrogateKey = $response->headers->get('Surrogate-Key');
        self::assertNotNull($surrogateKey);

        // Split keys by space
        $keys = explode(' ', $surrogateKey);

        // Should have 3 keys: type, type:id, type:id:rel
        self::assertCount(3, $keys);
        self::assertContains('articles', $keys);
        self::assertContains('articles:1', $keys);
        self::assertContains('articles:1:author', $keys);
    }

    public function testRelatedResourceResponseIncludesSurrogateKeys(): void
    {
        $request = Request::create('/api/articles/1/author', 'GET');
        $request->attributes->set('_route', 'jsonapi.related');
        $request->attributes->set('type', 'articles');
        $request->attributes->set('id', '1');
        $request->attributes->set('rel', 'author');

        $response = $this->relatedController()($request, 'articles', '1', 'author');

        // Apply cache headers using subscriber
        $this->applyCacheHeaders($request, $response);

        // Check that Surrogate-Key header is present
        self::assertTrue($response->headers->has('Surrogate-Key'), 'Response should have Surrogate-Key header');

        $surrogateKey = $response->headers->get('Surrogate-Key');
        self::assertNotNull($surrogateKey);

        // Should contain keys for the source resource
        self::assertStringContainsString('articles', $surrogateKey);
        self::assertStringContainsString('articles:1', $surrogateKey);
    }

    /**
     * Helper method to apply cache headers to response using CachePreconditionsSubscriber.
     */
    private function applyCacheHeaders(Request $request, \Symfony\Component\HttpFoundation\Response $response): void
    {
        $config = [
            'enabled' => true,
            'etag' => ['weak_for_collections' => true],
        ];

        $headersConfig = [
            'headers' => [
                'public' => true,
                'max_age' => 0,
            ],
            'vary' => [
                'accept' => true,
            ],
            'surrogate_keys' => [
                'enabled' => true,
                'header_name' => 'Surrogate-Key',
            ],
        ];

        $cacheKeyBuilder = new CacheKeyBuilder();
        $etagGenerator = new HashEtagGenerator();
        $lastModified = new LastModifiedResolver();
        $conditional = new ConditionalRequestEvaluator($this->errorMapper(), []);
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

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }
}
