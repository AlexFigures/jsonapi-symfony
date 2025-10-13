<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Http;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\CachePreconditionsSubscriber;
use AlexFigures\Symfony\Http\Cache\CacheKeyBuilder;
use AlexFigures\Symfony\Http\Cache\ConditionalRequestEvaluator;
use AlexFigures\Symfony\Http\Cache\HashEtagGenerator;
use AlexFigures\Symfony\Http\Cache\HeadersApplier;
use AlexFigures\Symfony\Http\Cache\LastModifiedResolver;
use AlexFigures\Symfony\Http\Cache\SurrogateKeyBuilder;
use AlexFigures\Symfony\Http\Exception\PreconditionFailedException;
use AlexFigures\Symfony\Http\Exception\PreconditionRequiredException;
use AlexFigures\Symfony\Tests\Fixtures\Model\Article;
use AlexFigures\Symfony\Tests\Fixtures\Model\Author;
use AlexFigures\Symfony\Tests\Fixtures\Model\Tag;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(CachePreconditionsSubscriber::class)]
#[CoversClass(ConditionalRequestEvaluator::class)]
#[CoversClass(PreconditionFailedException::class)]
#[CoversClass(PreconditionRequiredException::class)]
final class PreconditionsOnWritesTest extends JsonApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $author = new Author(id: '1', name: 'John Doe');
        $tag1 = new Tag(id: '1', name: 'PHP');

        $article = new Article(
            '1',
            'Original Title',
            new \DateTimeImmutable('2024-01-01 12:00:00'),
            $author,
            $tag1
        );

        $this->repository()->save('authors', $author);
        $this->repository()->save('tags', $tag1);
        $this->repository()->save('articles', $article);
    }

    public function testPatchWithMatchingIfMatchSucceeds(): void
    {
        // First, get the current ETag
        $getRequest = Request::create('/api/articles/1', 'GET');
        $getRequest->attributes->set('_route', 'jsonapi.resource');

        $getResponse = ($this->resourceController())($getRequest, 'articles', '1');
        $this->applyCacheHeaders($getRequest, $getResponse);

        $etag = $getResponse->headers->get('ETag');
        self::assertNotNull($etag);

        // Now PATCH with matching If-Match
        $patchRequest = Request::create('/api/articles/1', 'PATCH', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json',
            'HTTP_IF_MATCH' => $etag,
        ], content: json_encode([
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => ['title' => 'Updated Title'],
            ],
        ]));
        $patchRequest->attributes->set('_route', 'jsonapi.resource');

        // Evaluate preconditions BEFORE executing controller
        $tempResponse = new Response();
        $this->applyCacheHeaders($patchRequest, $tempResponse, requireIfMatch: true);

        // If no exception was thrown, execute the controller
        $patchResponse = ($this->updateController())($patchRequest, 'articles', '1');

        self::assertSame(200, $patchResponse->getStatusCode(), 'PATCH with matching If-Match should succeed');

        $data = json_decode($patchResponse->getContent(), true);
        self::assertSame('Updated Title', $data['data']['attributes']['title']);
    }

    public function testPatchWithMismatchedIfMatchFails(): void
    {
        // Get current resource to generate real ETag
        $getRequest = Request::create('/api/articles/1', 'GET');
        $getRequest->attributes->set('_route', 'jsonapi.resource');
        $getResponse = ($this->resourceController())($getRequest, 'articles', '1');
        $this->applyCacheHeaders($getRequest, $getResponse);

        $request = Request::create('/api/articles/1', 'PATCH', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json',
            'HTTP_IF_MATCH' => '"wrong-etag"',
        ], content: json_encode([
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => ['title' => 'Updated Title'],
            ],
        ]));
        $request->attributes->set('_route', 'jsonapi.resource');

        $this->expectException(PreconditionFailedException::class);

        // Evaluate preconditions with real response
        $response = ($this->resourceController())($request, 'articles', '1');
        $this->applyCacheHeaders($request, $response, requireIfMatch: true);
    }

    public function testPatchWithIfMatchWildcardSucceeds(): void
    {
        $request = Request::create('/api/articles/1', 'PATCH', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json',
            'HTTP_IF_MATCH' => '*',
        ], content: json_encode([
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => ['title' => 'Updated Title'],
            ],
        ]));
        $request->attributes->set('_route', 'jsonapi.resource');

        $response = ($this->updateController())($request, 'articles', '1');
        $this->applyCacheHeaders($request, $response, requireIfMatch: true);

        self::assertSame(200, $response->getStatusCode(), 'PATCH with If-Match: * should succeed');
    }

    public function testPatchWithWeakEtagInIfMatchFails(): void
    {
        // Get current resource to generate real ETag
        $getRequest = Request::create('/api/articles/1', 'GET');
        $getRequest->attributes->set('_route', 'jsonapi.resource');
        $getResponse = ($this->resourceController())($getRequest, 'articles', '1');
        $this->applyCacheHeaders($getRequest, $getResponse);

        $request = Request::create('/api/articles/1', 'PATCH', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json',
            'HTTP_IF_MATCH' => 'W/"weak-etag"',
        ], content: json_encode([
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => ['title' => 'Updated Title'],
            ],
        ]));
        $request->attributes->set('_route', 'jsonapi.resource');

        $this->expectException(PreconditionFailedException::class);

        // Evaluate preconditions with real response
        $response = ($this->resourceController())($request, 'articles', '1');
        $this->applyCacheHeaders($request, $response, requireIfMatch: true);
    }

    public function testPatchWithoutIfMatchFailsWhenRequired(): void
    {
        $request = Request::create('/api/articles/1', 'PATCH', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], content: json_encode([
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => ['title' => 'Updated Title'],
            ],
        ]));
        $request->attributes->set('_route', 'jsonapi.resource');

        $this->expectException(PreconditionRequiredException::class);

        $response = ($this->updateController())($request, 'articles', '1');
        $this->applyCacheHeaders($request, $response, requireIfMatch: true);
    }

    public function testDeleteWithMatchingIfMatchSucceeds(): void
    {
        // First, get the current ETag
        $getRequest = Request::create('/api/articles/1', 'GET');
        $getRequest->attributes->set('_route', 'jsonapi.resource');

        $getResponse = ($this->resourceController())($getRequest, 'articles', '1');
        $this->applyCacheHeaders($getRequest, $getResponse);

        $etag = $getResponse->headers->get('ETag');
        self::assertNotNull($etag);

        // Now DELETE with matching If-Match
        $deleteRequest = Request::create('/api/articles/1', 'DELETE', server: [
            'HTTP_IF_MATCH' => $etag,
        ]);
        $deleteRequest->attributes->set('_route', 'jsonapi.resource');

        // Evaluate preconditions BEFORE executing controller
        $tempResponse = new Response();
        $tempResponse->setContent($getResponse->getContent()); // Use same content for ETag generation
        $this->applyCacheHeaders($deleteRequest, $tempResponse, requireIfMatch: true);

        // If no exception was thrown, execute the controller
        $deleteResponse = ($this->deleteController())('articles', '1');

        self::assertSame(204, $deleteResponse->getStatusCode(), 'DELETE with matching If-Match should succeed');
    }

    public function testDeleteWithMismatchedIfMatchFails(): void
    {
        // Get current resource to generate real ETag
        $getRequest = Request::create('/api/articles/1', 'GET');
        $getRequest->attributes->set('_route', 'jsonapi.resource');
        $getResponse = ($this->resourceController())($getRequest, 'articles', '1');
        $this->applyCacheHeaders($getRequest, $getResponse);

        $request = Request::create('/api/articles/1', 'DELETE', server: [
            'HTTP_IF_MATCH' => '"wrong-etag"',
        ]);
        $request->attributes->set('_route', 'jsonapi.resource');

        $this->expectException(PreconditionFailedException::class);

        // For DELETE, we need to get the resource first to generate ETag
        $currentResponse = ($this->resourceController())($request, 'articles', '1');
        $this->applyCacheHeaders($request, $currentResponse, requireIfMatch: true);
    }

    public function testPatchWithIfUnmodifiedSinceAndOldDateFails(): void
    {
        $request = Request::create('/api/articles/1', 'PATCH', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json',
            'HTTP_IF_UNMODIFIED_SINCE' => 'Mon, 01 Jan 2020 12:00:00 GMT', // Old date
        ], content: json_encode([
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => ['title' => 'Updated Title'],
            ],
        ]));
        $request->attributes->set('_route', 'jsonapi.resource');

        $this->expectException(PreconditionFailedException::class);

        // Get current resource to generate response with Last-Modified
        $currentResponse = ($this->resourceController())($request, 'articles', '1');
        $currentResponse->headers->set('Last-Modified', 'Mon, 01 Jan 2024 12:00:00 GMT');
        $this->applyCacheHeaders($request, $currentResponse);
    }

    public function testPatchWithIfUnmodifiedSinceAndCurrentDateSucceeds(): void
    {
        $request = Request::create('/api/articles/1', 'PATCH', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json',
            'HTTP_IF_UNMODIFIED_SINCE' => 'Mon, 01 Jan 2025 12:00:00 GMT', // Future date
        ], content: json_encode([
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => ['title' => 'Updated Title'],
            ],
        ]));
        $request->attributes->set('_route', 'jsonapi.resource');

        $response = ($this->updateController())($request, 'articles', '1');
        $response->headers->set('Last-Modified', 'Mon, 01 Jan 2024 12:00:00 GMT');
        $this->applyCacheHeaders($request, $response);

        self::assertSame(200, $response->getStatusCode(), 'PATCH with If-Unmodified-Since and current date should succeed');
    }

    public function testPreconditionFailedErrorHasCorrectStructure(): void
    {
        // Get current resource to generate real ETag
        $getRequest = Request::create('/api/articles/1', 'GET');
        $getRequest->attributes->set('_route', 'jsonapi.resource');
        $getResponse = ($this->resourceController())($getRequest, 'articles', '1');
        $this->applyCacheHeaders($getRequest, $getResponse);

        $request = Request::create('/api/articles/1', 'PATCH', server: [
            'CONTENT_TYPE' => 'application/vnd.api+json',
            'HTTP_IF_MATCH' => '"wrong-etag"',
        ], content: json_encode([
            'data' => [
                'type' => 'articles',
                'id' => '1',
                'attributes' => ['title' => 'Updated Title'],
            ],
        ]));
        $request->attributes->set('_route', 'jsonapi.resource');

        try {
            // Use GET response to generate ETag for precondition check
            $currentResponse = ($this->resourceController())($request, 'articles', '1');
            $this->applyCacheHeaders($request, $currentResponse, requireIfMatch: true);
            self::fail('Expected PreconditionFailedException to be thrown');
        } catch (PreconditionFailedException $exception) {
            $errors = $exception->getErrors();
            self::assertNotEmpty($errors, 'Exception should have errors');
            self::assertSame('412', $errors[0]->status, sprintf('Expected status 412, got %s. Error detail: %s', $errors[0]->status, $errors[0]->detail ?? 'N/A'));
            self::assertStringContainsString('If-Match', $errors[0]->detail ?? '');
        } catch (\Throwable $e) {
            self::fail(sprintf('Expected PreconditionFailedException, got %s: %s', get_class($e), $e->getMessage()));
        }
    }

    /**
     * Apply cache headers and evaluate preconditions
     */
    private function applyCacheHeaders(
        Request $request,
        Response $response,
        bool $requireIfMatch = false
    ): void {
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
                'require_if_match_on_write' => $requireIfMatch,
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
