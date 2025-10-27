<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Response;

use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Response\JsonApiResponseFactory;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ArticleStatus;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration tests for JsonApiResponseFactory.
 *
 * Tests real-world usage scenarios with actual controllers and database entities.
 */
final class JsonApiResponseFactoryIntegrationTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private JsonApiResponseFactory $factory;

    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set up routing
        $routes = new RouteCollection();
        $routes->add('jsonapi.articles.index', new Route('/api/articles'));
        $routes->add('jsonapi.articles.show', new Route('/api/articles/{id}'));
        $routes->add('jsonapi.articles.relationships.author.show', new Route('/api/articles/{id}/relationships/author'));
        $routes->add('jsonapi.articles.relationships.tags.show', new Route('/api/articles/{id}/relationships/tags'));
        $routes->add('jsonapi.articles.related.author', new Route('/api/articles/{id}/author'));
        $routes->add('jsonapi.articles.related.tags', new Route('/api/articles/{id}/tags'));
        $routes->add('jsonapi.media.index', new Route('/api/media'));
        $routes->add('jsonapi.media.show', new Route('/api/media/{id}'));

        $context = new RequestContext();
        $context->setScheme('https');
        $context->setHost('api.example.com');

        $urlGenerator = new UrlGenerator($routes, $context);
        $linkGenerator = new LinkGenerator($urlGenerator);

        $errorBuilder = new ErrorBuilder(useDefaultTitleMap: true);

        $documentBuilder = new DocumentBuilder(
            $this->registry,
            $this->accessor,
            $linkGenerator,
            relationshipLinkageMode: 'always',
            limits: null,
        );

        $this->factory = new JsonApiResponseFactory(
            $documentBuilder,
            $linkGenerator,
            $errorBuilder,
            $this->registry
        );
    }

    public function testCreatedResourceWithMeta(): void
    {
        // Create article
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Test body');
        $article->setStatus(ArticleStatus::DRAFT);

        $this->em->persist($article);
        $this->em->flush();

        // Build response
        $response = $this->factory->created('articles', $article)
            ->withMeta([
                'createdAt' => time(),
                'version' => 1,
            ])
            ->build();

        // Assertions
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));
        self::assertNotNull($response->headers->get('Location'));

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('jsonapi', $data);
        self::assertSame(['version' => '1.1'], $data['jsonapi']);
        self::assertArrayHasKey('data', $data);
        self::assertSame('articles', $data['data']['type']);
        self::assertSame((string) $article->getId(), $data['data']['id']);
        self::assertArrayHasKey('meta', $data);
        self::assertArrayHasKey('createdAt', $data['meta']);
        self::assertSame(1, $data['meta']['version']);
    }

    public function testResourceWithLinks(): void
    {
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Test body');
        $article->setStatus(ArticleStatus::PUBLISHED);

        $this->em->persist($article);
        $this->em->flush();

        $response = $this->factory->resource('articles', $article)
            ->withLinks([
                'comments' => "/api/articles/{$article->getId()}/comments",
                'related' => "/api/articles/{$article->getId()}/related",
            ])
            ->build();

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('links', $data);
        self::assertArrayHasKey('comments', $data['links']);
        self::assertArrayHasKey('related', $data['links']);
    }

    public function testCollectionWithPagination(): void
    {
        // Create multiple articles
        $articles = [];
        for ($i = 1; $i <= 5; $i++) {
            $article = new Article();
            $article->setTitle("Article {$i}");
            $article->setContent("Body {$i}");
            $article->setStatus(ArticleStatus::PUBLISHED);
            $this->em->persist($article);
            $articles[] = $article;
        }
        $this->em->flush();

        $response = $this->factory->collection('articles', array_slice($articles, 0, 3))
            ->withTotalItems(5)
            ->withMeta(['page' => 1, 'perPage' => 3])
            ->build();

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $data);
        self::assertCount(3, $data['data']);
        self::assertArrayHasKey('meta', $data);
        self::assertSame(5, $data['meta']['total']);
        self::assertSame(1, $data['meta']['page']);
    }

    public function testNoContentResponse(): void
    {
        $response = $this->factory->noContent();

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testAcceptedWithMeta(): void
    {
        $response = $this->factory->accepted()
            ->withMeta([
                'jobId' => 'abc-123',
                'status' => 'queued',
                'estimatedTime' => 300,
            ])
            ->withLinks(['status' => '/api/jobs/abc-123'])
            ->build();

        self::assertSame(202, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('meta', $data);
        self::assertSame('abc-123', $data['meta']['jobId']);
        self::assertSame('queued', $data['meta']['status']);
        self::assertArrayHasKey('links', $data);
        self::assertSame('/api/jobs/abc-123', $data['links']['status']);
    }

    public function testErrorWithCodeAndSource(): void
    {
        $response = $this->factory->error(400, 'File is required')
            ->withCode('file_required')
            ->withTitle('Missing File')
            ->withSource(pointer: '/data/attributes/file')
            ->withMeta(['hint' => 'Use multipart/form-data'])
            ->build();

        self::assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('errors', $data);
        self::assertCount(1, $data['errors']);

        $error = $data['errors'][0];
        self::assertSame('400', $error['status']);
        self::assertSame('file_required', $error['code']);
        self::assertSame('Missing File', $error['title']);
        self::assertSame('File is required', $error['detail']);
        self::assertArrayHasKey('source', $error);
        self::assertSame('/data/attributes/file', $error['source']['pointer']);
        self::assertArrayHasKey('meta', $error);
        self::assertSame('Use multipart/form-data', $error['meta']['hint']);
    }

    public function testValidationErrors(): void
    {
        $response = $this->factory->validationErrors([
            [
                'pointer' => '/data/attributes/email',
                'detail' => 'Invalid email format',
                'code' => 'invalid_email',
            ],
            [
                'pointer' => '/data/attributes/age',
                'detail' => 'Must be at least 18',
                'code' => 'age_too_low',
                'title' => 'Age Validation Failed',
            ],
        ])->build();

        self::assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('errors', $data);
        self::assertCount(2, $data['errors']);

        self::assertSame('invalid_email', $data['errors'][0]['code']);
        self::assertSame('Invalid email format', $data['errors'][0]['detail']);
        self::assertSame('/data/attributes/email', $data['errors'][0]['source']['pointer']);

        self::assertSame('age_too_low', $data['errors'][1]['code']);
        self::assertSame('Age Validation Failed', $data['errors'][1]['title']);
        self::assertSame('Must be at least 18', $data['errors'][1]['detail']);
    }

    public function testCustomHeadersAreIncluded(): void
    {
        $article = new Article();
        $article->setTitle('Test');
        $article->setContent('Body');
        $article->setStatus(ArticleStatus::DRAFT);

        $this->em->persist($article);
        $this->em->flush();

        $response = $this->factory->resource('articles', $article)
            ->withHeader('X-Custom-Header', 'custom-value')
            ->withHeader('X-Request-Id', 'req-123')
            ->build();

        self::assertSame('custom-value', $response->headers->get('X-Custom-Header'));
        self::assertSame('req-123', $response->headers->get('X-Request-Id'));
    }

    public function testCustomStatusCode(): void
    {
        $article = new Article();
        $article->setTitle('Test');
        $article->setContent('Body');
        $article->setStatus(ArticleStatus::DRAFT);

        $this->em->persist($article);
        $this->em->flush();

        $response = $this->factory->resource('articles', $article)
            ->withStatus(206) // Partial Content
            ->build();

        self::assertSame(206, $response->getStatusCode());
    }

    public function testRealWorldFileUploadScenario(): void
    {
        // Simulate a file upload controller response
        $media = new class () {
            public string $id = 'media-123';
            public string $filename = 'photo.jpg';
            public int $size = 1024000;
            public string $mimeType = 'image/jpeg';
            public int $width = 1920;
            public int $height = 1080;
        };

        // Register temporary media resource
        $mediaMetadata = new ResourceMetadata(
            'media',
            $media::class,
            [
                'filename' => new AttributeMetadata('filename'),
                'size' => new AttributeMetadata('size'),
                'mimeType' => new AttributeMetadata('mimeType'),
                'width' => new AttributeMetadata('width'),
                'height' => new AttributeMetadata('height'),
            ],
            []
        );

        $tempRegistry = new class ($this->registry, $mediaMetadata) implements ResourceRegistryInterface {
            public function __construct(
                private ResourceRegistryInterface $original,
                private ResourceMetadata $media
            ) {
            }

            public function getByType(string $type): ResourceMetadata
            {
                return $type === 'media' ? $this->media : $this->original->getByType($type);
            }

            public function hasType(string $type): bool
            {
                return $type === 'media' || $this->original->hasType($type);
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                return $this->original->getByClass($class);
            }

            public function all(): array
            {
                return array_merge($this->original->all(), [$this->media]);
            }
        };

        // Recreate factory with temp registry
        $routes = new RouteCollection();
        $routes->add('jsonapi.media.show', new Route('/api/media/{id}'));
        $context = new RequestContext();
        $urlGenerator = new UrlGenerator($routes, $context);
        $linkGenerator = new LinkGenerator($urlGenerator);
        $errorBuilder = new ErrorBuilder(false);
        $documentBuilder = new DocumentBuilder($tempRegistry, $this->accessor, $linkGenerator, 'always', null);

        $factory = new JsonApiResponseFactory($documentBuilder, $linkGenerator, $errorBuilder, $tempRegistry);

        $response = $factory->created('media', $media)
            ->withMeta([
                'uploadedAt' => '2024-01-15T10:30:00Z',
                'processingTime' => 0.234,
            ])
            ->withLinks([
                'thumbnail' => '/api/media/media-123/thumbnail',
                'download' => '/api/media/media-123/download',
            ])
            ->withHeader('X-Upload-Id', 'upload-456')
            ->build();

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('upload-456', $response->headers->get('X-Upload-Id'));

        $data = json_decode($response->getContent(), true);
        self::assertSame('media', $data['data']['type']);
        self::assertSame('media-123', $data['data']['id']);
        self::assertSame('photo.jpg', $data['data']['attributes']['filename']);
        self::assertSame(1024000, $data['data']['attributes']['size']);
        self::assertArrayHasKey('uploadedAt', $data['meta']);
        self::assertArrayHasKey('thumbnail', $data['links']);
        self::assertArrayHasKey('download', $data['links']);
    }
}
