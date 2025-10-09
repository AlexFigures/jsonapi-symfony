<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Relationships;

use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-006: Relationship Links Validation
 *
 * Tests that relationship objects contain required links:
 * - self link pointing to the relationship endpoint
 * - related link pointing to the related resource(s)
 * - links are properly formatted URLs
 */
final class RelationshipLinksTest extends JsonApiTestCase
{
    public function testRelationshipSelfLinkIsPresent(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{relationships: array<string, array{links: array<string, string>}>}} $document */
        $document = $this->decode($response);

        self::assertArrayHasKey('relationships', $document['data']);
        $relationships = $document['data']['relationships'];

        // Check author relationship (to-one)
        self::assertArrayHasKey('author', $relationships);
        self::assertArrayHasKey('links', $relationships['author']);
        self::assertArrayHasKey('self', $relationships['author']['links']);

        $selfLink = $relationships['author']['links']['self'];
        self::assertStringContainsString('/api/articles/1/relationships/author', $selfLink);

        // Check tags relationship (to-many)
        self::assertArrayHasKey('tags', $relationships);
        self::assertArrayHasKey('links', $relationships['tags']);
        self::assertArrayHasKey('self', $relationships['tags']['links']);

        $selfLink = $relationships['tags']['links']['self'];
        self::assertStringContainsString('/api/articles/1/relationships/tags', $selfLink);
    }

    public function testRelationshipRelatedLinkIsPresent(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{relationships: array<string, array{links: array<string, string>}>}} $document */
        $document = $this->decode($response);

        self::assertArrayHasKey('relationships', $document['data']);
        $relationships = $document['data']['relationships'];

        // Check author relationship (to-one)
        self::assertArrayHasKey('author', $relationships);
        self::assertArrayHasKey('links', $relationships['author']);
        self::assertArrayHasKey('related', $relationships['author']['links']);

        $relatedLink = $relationships['author']['links']['related'];
        self::assertStringContainsString('/api/articles/1/author', $relatedLink);

        // Check tags relationship (to-many)
        self::assertArrayHasKey('tags', $relationships);
        self::assertArrayHasKey('links', $relationships['tags']);
        self::assertArrayHasKey('related', $relationships['tags']['links']);

        $relatedLink = $relationships['tags']['links']['related'];
        self::assertStringContainsString('/api/articles/1/tags', $relatedLink);
    }

    public function testRelationshipLinksAreValid(): void
    {
        $request = Request::create('/api/articles/1', 'GET');
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{relationships: array<string, array{links: array<string, string>}>}} $document */
        $document = $this->decode($response);

        $relationships = $document['data']['relationships'];

        foreach ($relationships as $relationshipName => $relationship) {
            self::assertArrayHasKey('links', $relationship, "Relationship '{$relationshipName}' must have links");

            $links = $relationship['links'];

            // Validate self link
            self::assertArrayHasKey('self', $links, "Relationship '{$relationshipName}' must have self link");
            self::assertIsString($links['self']);
            self::assertMatchesRegularExpression(
                '#^https?://.+/api/articles/1/relationships/' . preg_quote($relationshipName, '#') . '$#',
                $links['self'],
                "Self link for '{$relationshipName}' must be a valid URL"
            );

            // Validate related link
            self::assertArrayHasKey('related', $links, "Relationship '{$relationshipName}' must have related link");
            self::assertIsString($links['related']);
            self::assertMatchesRegularExpression(
                '#^https?://.+/api/articles/1/' . preg_quote($relationshipName, '#') . '$#',
                $links['related'],
                "Related link for '{$relationshipName}' must be a valid URL"
            );
        }
    }

    public function testRelationshipLinksInCollectionResponse(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $response = $this->collectionController()($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: list<array{id: string, relationships: array<string, array{links: array<string, string>}>}>} $document */
        $document = $this->decode($response);

        self::assertIsArray($document['data']);
        self::assertNotEmpty($document['data']);

        foreach ($document['data'] as $resource) {
            self::assertArrayHasKey('relationships', $resource);
            $relationships = $resource['relationships'];
            $resourceId = $resource['id'];

            foreach ($relationships as $relationshipName => $relationship) {
                self::assertArrayHasKey('links', $relationship);
                $links = $relationship['links'];

                // Validate self link
                self::assertArrayHasKey('self', $links);
                self::assertStringContainsString("/api/articles/{$resourceId}/relationships/{$relationshipName}", $links['self']);

                // Validate related link
                self::assertArrayHasKey('related', $links);
                self::assertStringContainsString("/api/articles/{$resourceId}/{$relationshipName}", $links['related']);
            }
        }
    }

    public function testRelationshipLinksWithInclude(): void
    {
        $request = Request::create('/api/articles/1?include=author', 'GET');
        $response = $this->resourceController()($request, 'articles', '1');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{data: array{relationships: array<string, array{links: array<string, string>, data: mixed}>}} $document */
        $document = $this->decode($response);

        $relationships = $document['data']['relationships'];

        // Even with include, links must still be present
        self::assertArrayHasKey('author', $relationships);
        self::assertArrayHasKey('links', $relationships['author']);
        self::assertArrayHasKey('self', $relationships['author']['links']);
        self::assertArrayHasKey('related', $relationships['author']['links']);

        // And data should also be present when included
        self::assertArrayHasKey('data', $relationships['author']);
    }
}
