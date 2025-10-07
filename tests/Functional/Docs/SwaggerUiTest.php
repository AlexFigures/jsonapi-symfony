<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Docs;

use JsonApi\Symfony\Http\Controller\SwaggerUiController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SwaggerUiTest extends TestCase
{
    public function testSwaggerUiRendersWhenEnabled(): void
    {
        $config = [
            'enabled' => true,
            'route' => '/_jsonapi/docs',
            'spec_url' => '/_jsonapi/openapi.json',
            'theme' => 'swagger',
        ];

        $controller = new SwaggerUiController($config);
        $request = Request::create('/_jsonapi/docs', 'GET');

        $response = $controller($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        self::assertNotFalse($content);

        // Verify Swagger UI is loaded
        self::assertStringContainsString('swagger-ui-dist', $content);
        self::assertStringContainsString('SwaggerUIBundle', $content);
        self::assertStringContainsString('/_jsonapi/openapi.json', $content);
        self::assertStringContainsString('JSON:API Documentation', $content);
    }

    public function testRedocRendersWhenThemeIsRedoc(): void
    {
        $config = [
            'enabled' => true,
            'route' => '/_jsonapi/docs',
            'spec_url' => '/_jsonapi/openapi.json',
            'theme' => 'redoc',
        ];

        $controller = new SwaggerUiController($config);
        $request = Request::create('/_jsonapi/docs', 'GET');

        $response = $controller($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        self::assertNotFalse($content);

        // Verify Redoc is loaded
        self::assertStringContainsString('<redoc', $content);
        self::assertStringContainsString('redoc.standalone.js', $content);
        self::assertStringContainsString('/_jsonapi/openapi.json', $content);
        self::assertStringContainsString('JSON:API Documentation', $content);
    }

    public function testThrowsNotFoundWhenDisabled(): void
    {
        $config = [
            'enabled' => false,
            'route' => '/_jsonapi/docs',
            'spec_url' => '/_jsonapi/openapi.json',
            'theme' => 'swagger',
        ];

        $controller = new SwaggerUiController($config);
        $request = Request::create('/_jsonapi/docs', 'GET');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('API documentation UI is disabled.');

        $controller($request);
    }

    public function testCustomSpecUrlIsUsed(): void
    {
        $config = [
            'enabled' => true,
            'route' => '/_jsonapi/docs',
            'spec_url' => '/custom/openapi.json',
            'theme' => 'swagger',
        ];

        $controller = new SwaggerUiController($config);
        $request = Request::create('/_jsonapi/docs', 'GET');

        $response = $controller($request);

        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertStringContainsString('/custom/openapi.json', $content);
    }

    public function testSwaggerUiHasInteractiveFeatures(): void
    {
        $config = [
            'enabled' => true,
            'route' => '/_jsonapi/docs',
            'spec_url' => '/_jsonapi/openapi.json',
            'theme' => 'swagger',
        ];

        $controller = new SwaggerUiController($config);
        $request = Request::create('/_jsonapi/docs', 'GET');

        $response = $controller($request);

        $content = $response->getContent();
        self::assertNotFalse($content);

        // Verify interactive features are enabled
        self::assertStringContainsString('tryItOutEnabled: true', $content);
        self::assertStringContainsString('deepLinking: true', $content);
        self::assertStringContainsString('filter: true', $content);
    }
}

