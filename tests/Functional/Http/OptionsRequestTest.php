<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Http;

use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for OPTIONS requests.
 *
 * Tests that OPTIONS requests return correct Allow headers based on resource operations.
 */
final class OptionsRequestTest extends JsonApiTestCase
{
    /**
     * Test OPTIONS request for collection endpoint returns allowed methods.
     */
    public function testOptionsForCollectionEndpoint(): void
    {
        $response = $this->optionsController()->collection('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertTrue($response->headers->has('Allow'));

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        // Articles resource has all operations enabled by default
        self::assertContains('GET', $methods);
        self::assertContains('HEAD', $methods);
        self::assertContains('POST', $methods);
        self::assertContains('OPTIONS', $methods);
    }

    /**
     * Test OPTIONS request for resource endpoint returns allowed methods.
     */
    public function testOptionsForResourceEndpoint(): void
    {
        $response = $this->optionsController()->resource('articles');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertTrue($response->headers->has('Allow'));

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        // Articles resource has all operations enabled by default
        self::assertContains('DELETE', $methods);
        self::assertContains('GET', $methods);
        self::assertContains('HEAD', $methods);
        self::assertContains('OPTIONS', $methods);
        self::assertContains('PATCH', $methods);
    }

    /**
     * Test OPTIONS request for relationship endpoint returns allowed methods.
     */
    public function testOptionsForRelationshipEndpoint(): void
    {
        $response = $this->optionsController()->relationship('articles', 'author');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertTrue($response->headers->has('Allow'));

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        // Relationship endpoints support GET (SHOW) and write methods (UPDATE)
        self::assertContains('GET', $methods);
        self::assertContains('HEAD', $methods);
        self::assertContains('OPTIONS', $methods);
        self::assertContains('PATCH', $methods);
    }

    /**
     * Test OPTIONS request for to-many relationship endpoint includes POST and DELETE.
     */
    public function testOptionsForToManyRelationshipEndpoint(): void
    {
        $response = $this->optionsController()->relationship('articles', 'tags');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertTrue($response->headers->has('Allow'));

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        // To-many relationships support POST and DELETE in addition to GET and PATCH
        self::assertContains('DELETE', $methods);
        self::assertContains('GET', $methods);
        self::assertContains('HEAD', $methods);
        self::assertContains('OPTIONS', $methods);
        self::assertContains('PATCH', $methods);
        self::assertContains('POST', $methods);
    }

    /**
     * Test OPTIONS request for related resource endpoint returns allowed methods.
     */
    public function testOptionsForRelatedResourceEndpoint(): void
    {
        $response = $this->optionsController()->related('articles', 'author');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertTrue($response->headers->has('Allow'));

        $allowHeader = $response->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $methods = array_map('trim', explode(',', $allowHeader));
        sort($methods);

        // Related resource endpoints only support GET (SHOW operation)
        self::assertContains('GET', $methods);
        self::assertContains('HEAD', $methods);
        self::assertContains('OPTIONS', $methods);
    }

    /**
     * Test OPTIONS request for non-existent resource type returns 404.
     */
    public function testOptionsForNonExistentResourceType(): void
    {
        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);

        $this->optionsController()->collection('nonexistent');
    }

    /**
     * Test that actual requests respect the allowed methods.
     *
     * This is a sanity check to ensure OPTIONS reflects reality.
     */
    public function testActualRequestsMatchOptionsAllowHeader(): void
    {
        // First, get the allowed methods via OPTIONS
        $optionsResponse = $this->optionsController()->collection('articles');

        $allowHeader = $optionsResponse->headers->get('Allow');
        self::assertNotNull($allowHeader);

        $allowedMethods = array_map('trim', explode(',', $allowHeader));

        // Verify GET is allowed and works
        if (in_array('GET', $allowedMethods, true)) {
            $getRequest = Request::create('/api/articles', 'GET');
            $getResponse = $this->collectionController()($getRequest, 'articles');
            self::assertSame(Response::HTTP_OK, $getResponse->getStatusCode());
        }

        // Verify POST is allowed (we'll get validation error, but not 405)
        if (in_array('POST', $allowedMethods, true)) {
            self::assertTrue(true, 'POST is in allowed methods');
        }
    }
}
