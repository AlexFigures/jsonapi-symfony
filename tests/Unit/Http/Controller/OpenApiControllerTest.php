<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Http\Controller;

use AlexFigures\Symfony\Docs\OpenApi\OpenApiSpecGenerator;
use AlexFigures\Symfony\Http\Controller\OpenApiController;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OpenApiControllerTest extends TestCase
{
    public function testGeneratesSpecificationWhenEnabled(): void
    {
        $generator = $this->createGenerator();

        $controller = new OpenApiController(
            $generator,
            ['enabled' => true],
        );

        $response = ($controller)();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/vnd.oai.openapi+json', $response->headers->get('Content-Type'));

        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('3.1.0', $content['openapi']);
        self::assertSame('Test API', $content['info']['title']);
        self::assertArrayHasKey('/api/articles', $content['paths']);
    }

    public function testThrowsNotFoundWhenDisabled(): void
    {
        $generator = $this->createGenerator();

        $controller = new OpenApiController(
            $generator,
            ['enabled' => false],
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('OpenAPI generation is disabled.');

        ($controller)();
    }

    public function testThrowsNotFoundWhenEnabledKeyMissing(): void
    {
        $generator = $this->createGenerator();

        $controller = new OpenApiController(
            $generator,
            [],
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('OpenAPI generation is disabled.');

        ($controller)();
    }

    public function testReturnsCorrectContentType(): void
    {
        $generator = $this->createGenerator();

        $controller = new OpenApiController(
            $generator,
            ['enabled' => true],
        );

        $response = ($controller)();

        // Verify OpenAPI-specific content type
        self::assertSame('application/vnd.oai.openapi+json', $response->headers->get('Content-Type'));
    }

    public function testReturnsValidJsonStructure(): void
    {
        $generator = $this->createGenerator();

        $controller = new OpenApiController(
            $generator,
            ['enabled' => true],
        );

        $response = ($controller)();
        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Verify all major OpenAPI sections are present
        self::assertArrayHasKey('openapi', $content);
        self::assertArrayHasKey('info', $content);
        self::assertArrayHasKey('servers', $content);
        self::assertArrayHasKey('paths', $content);
        self::assertArrayHasKey('components', $content);

        // Verify structure details
        self::assertSame('Test API', $content['info']['title']);
        self::assertSame('1.0.0', $content['info']['version']);
        self::assertCount(1, $content['servers']);
        self::assertArrayHasKey('/api/articles', $content['paths']);
    }

    private function createGenerator(): OpenApiSpecGenerator
    {
        $articleMetadata = new ResourceMetadata(
            'articles',
            Article::class,
            [
                'title' => new AttributeMetadata('title', types: ['string'], nullable: false),
            ],
            [],
            exposeId: true,
            idPropertyPath: 'id',
            routePrefix: '/api',
        );

        $registry = new class ($articleMetadata) implements ResourceRegistryInterface {
            public function __construct(private ResourceMetadata $article)
            {
            }

            public function getByType(string $type): ResourceMetadata
            {
                return match ($type) {
                    'articles' => $this->article,
                    default => throw new \LogicException('Unknown type: ' . $type),
                };
            }

            public function hasType(string $type): bool
            {
                return $type === 'articles';
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                return $class === Article::class ? $this->article : null;
            }

            public function all(): array
            {
                return [$this->article];
            }
        };

        return new OpenApiSpecGenerator(
            $registry,
            null, // No custom routes
            [
                'enabled' => true,
                'route' => '/_jsonapi/openapi.json',
                'title' => 'Test API',
                'version' => '1.0.0',
                'servers' => ['https://api.test'],
            ],
            '/api',
            'linkage',
        );
    }
}

final class Article
{
    public function __construct(
        public string $id,
        public string $title,
    ) {
    }
}
