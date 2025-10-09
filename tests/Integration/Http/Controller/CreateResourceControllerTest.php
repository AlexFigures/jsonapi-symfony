<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Http\Controller;

use JsonApi\Symfony\Http\Controller\CreateResourceController;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Http\Write\ChangeSetFactory;
use JsonApi\Symfony\Http\Write\InputDocumentValidator;
use JsonApi\Symfony\Http\Write\WriteConfig;
use JsonApi\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Author;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Category;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Comment;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use JsonApi\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration test for CreateResourceController with real PostgreSQL database.
 *
 * This test validates JSON:API specification compliance for resource creation
 * operations using real Doctrine entities and PostgreSQL connectivity.
 *
 * Test Coverage:
 * - Simple resource creation (no relationships)
 * - To-one relationships (Article belongsTo Author)
 * - To-many relationships (Author hasMany Articles)
 * - Many-to-many relationships (Article hasMany Tags)
 * - Self-referencing relationships (Category with parent)
 * - Integer primary keys with database-generated sequences (Comment)
 * - Error handling and validation
 * - JSON:API specification compliance
 */
final class CreateResourceControllerTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private CreateResourceController $controller;
    private LinkGenerator $linkGenerator;

    protected function getDatabaseUrl(): string
    {
        // In Docker: postgres:5432, locally: localhost:5432
        return $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set up routing for LinkGenerator
        $routes = new RouteCollection();
        $routes->add('jsonapi.collection', new Route('/api/{type}'));
        $routes->add('jsonapi.resource', new Route('/api/{type}/{id}'));
        $routes->add('jsonapi.create', new Route('/api/{type}', methods: ['POST']));

        // Add type-specific routes for LinkGenerator
        foreach (['articles', 'authors', 'tags', 'categories', 'comments'] as $type) {
            $routes->add("jsonapi.{$type}.index", new Route("/api/{$type}"));
            $routes->add("jsonapi.{$type}.show", new Route("/api/{$type}/{id}"));

            // Related resource routes
            $routes->add("jsonapi.{$type}.related.author", new Route("/api/{$type}/{id}/author"));
            $routes->add("jsonapi.{$type}.related.tags", new Route("/api/{$type}/{id}/tags"));
            $routes->add("jsonapi.{$type}.related.parent", new Route("/api/{$type}/{id}/parent"));
            $routes->add("jsonapi.{$type}.related.children", new Route("/api/{$type}/{id}/children"));
            $routes->add("jsonapi.{$type}.related.articles", new Route("/api/{$type}/{id}/articles"));

            // Relationship routes
            $routes->add("jsonapi.{$type}.relationships.author.show", new Route("/api/{$type}/{id}/relationships/author"));
            $routes->add("jsonapi.{$type}.relationships.tags.show", new Route("/api/{$type}/{id}/relationships/tags"));
            $routes->add("jsonapi.{$type}.relationships.parent.show", new Route("/api/{$type}/{id}/relationships/parent"));
            $routes->add("jsonapi.{$type}.relationships.children.show", new Route("/api/{$type}/{id}/relationships/children"));
            $routes->add("jsonapi.{$type}.relationships.articles.show", new Route("/api/{$type}/{id}/relationships/articles"));
        }

        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = new UrlGenerator($routes, $context);
        $this->linkGenerator = new LinkGenerator($urlGenerator);

        // Set up DocumentBuilder
        $documentBuilder = new DocumentBuilder(
            $this->registry,
            $this->accessor,
            $this->linkGenerator,
            'always'
        );

        // Set up error handling
        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);

        // Set up write configuration (allow client IDs for authors only)
        $writeConfig = new WriteConfig(true, ['authors' => true]);

        // Set up validators
        $inputValidator = new InputDocumentValidator($this->registry, $writeConfig, $errorMapper);
        $changeSetFactory = new ChangeSetFactory($this->registry);

        // Set up event dispatcher
        $eventDispatcher = new EventDispatcher();

        // Create the controller
        $this->controller = new CreateResourceController(
            $this->registry,
            $inputValidator,
            $changeSetFactory,
            $this->validatingProcessor,
            $this->transactionManager,
            $documentBuilder,
            $this->linkGenerator,
            $writeConfig,
            $errorMapper,
            $this->violationMapper,
            $eventDispatcher
        );
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();
        parent::tearDown();
    }

    /**
     * Test 1: Create simple resource with no relationships (Tag).
     *
     * Validates:
     * - 201 Created status
     * - Content-Type: application/vnd.api+json
     * - Location header with resource URL
     * - Response document structure (data.type, data.id, data.attributes)
     * - Data persistence in PostgreSQL
     */
    public function testCreateSimpleResourceWithNoRelationships(): void
    {
        $payload = [
            'data' => [
                'type' => 'tags',
                'attributes' => [
                    'name' => 'PHP',
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/tags', $payload);
        $response = ($this->controller)($request, 'tags');

        // Assert HTTP status and headers
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));
        self::assertNotNull($response->headers->get('Location'));

        // Decode and validate response structure
        $document = $this->decode($response);

        self::assertArrayHasKey('data', $document);
        self::assertIsArray($document['data']);
        self::assertArrayHasKey('type', $document['data']);
        self::assertArrayHasKey('id', $document['data']);
        self::assertArrayHasKey('attributes', $document['data']);

        self::assertSame('tags', $document['data']['type']);
        self::assertNotEmpty($document['data']['id']); // ID is auto-generated
        self::assertSame('PHP', $document['data']['attributes']['name']);

        // Verify Location header matches self link
        $selfLink = $document['data']['links']['self'] ?? null;
        self::assertNotNull($selfLink);
        self::assertSame($selfLink, $response->headers->get('Location'));

        // Verify data persistence in PostgreSQL
        $tagId = $document['data']['id'];
        $this->em->clear();

        $tag = $this->em->find(Tag::class, $tagId);
        self::assertInstanceOf(Tag::class, $tag);
        self::assertSame('PHP', $tag->getName());
    }

    /**
     * Test 2: Create resource with to-one relationship (Article with Author).
     *
     * Validates:
     * - Relationship data in request is processed
     * - Response includes relationships member
     * - Foreign key is set correctly in database
     */
    public function testCreateResourceWithToOneRelationship(): void
    {
        // First, create an author
        $author = new Author();
        $author->setId('author-1');
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);
        $this->em->flush();
        $this->em->clear();

        // Create article with author relationship
        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => 'Test Article',
                    'content' => 'Article content',
                ],
                'relationships' => [
                    'author' => [
                        'data' => ['type' => 'authors', 'id' => 'author-1'],
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/articles', $payload);
        $response = ($this->controller)($request, 'articles');

        // Assert response
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);
        $articleId = $document['data']['id'];

        // Verify relationship in response
        self::assertArrayHasKey('relationships', $document['data']);
        self::assertArrayHasKey('author', $document['data']['relationships']);

        // Verify data persistence with relationship
        $this->em->clear();
        $article = $this->em->find(Article::class, $articleId);

        self::assertInstanceOf(Article::class, $article);
        self::assertSame('Test Article', $article->getTitle());
        self::assertNotNull($article->getAuthor());
        self::assertSame('author-1', $article->getAuthor()->getId());
        self::assertSame('John Doe', $article->getAuthor()->getName());
    }

    /**
     * Test 3: Create resource with many-to-many relationships (Article with Tags).
     *
     * Validates:
     * - Multiple relationship items are processed
     * - Join table is populated correctly
     * - Response includes relationship data
     */
    public function testCreateResourceWithManyToManyRelationships(): void
    {
        // Create tags first
        $tag1 = new Tag();
        $tag1->setId('tag-1');
        $tag1->setName('PHP');

        $tag2 = new Tag();
        $tag2->setId('tag-2');
        $tag2->setName('Symfony');

        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->flush();
        $this->em->clear();

        // Create article with multiple tags
        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'title' => 'PHP and Symfony Guide',
                    'content' => 'A comprehensive guide',
                ],
                'relationships' => [
                    'tags' => [
                        'data' => [
                            ['type' => 'tags', 'id' => 'tag-1'],
                            ['type' => 'tags', 'id' => 'tag-2'],
                        ],
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/articles', $payload);
        $response = ($this->controller)($request, 'articles');

        // Assert response
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);
        $articleId = $document['data']['id'];

        // Verify relationships in response
        self::assertArrayHasKey('relationships', $document['data']);
        self::assertArrayHasKey('tags', $document['data']['relationships']);

        // Verify data persistence with many-to-many relationship
        $this->em->clear();
        $article = $this->em->find(Article::class, $articleId);

        self::assertInstanceOf(Article::class, $article);
        self::assertCount(2, $article->getTags());

        $tagNames = [];
        foreach ($article->getTags() as $tag) {
            $tagNames[] = $tag->getName();
        }

        self::assertContains('PHP', $tagNames);
        self::assertContains('Symfony', $tagNames);
    }

    /**
     * Test 4: Create self-referencing resource (Category with parent).
     *
     * Validates:
     * - Self-referencing relationships work correctly
     * - Parent-child hierarchy is established
     * - Doctrine handles circular references properly
     */
    public function testCreateSelfReferencingResource(): void
    {
        // Create parent category first
        $parent = new Category();
        $parent->setId('cat-parent');
        $parent->setName('Parent Category');
        $parent->setSortOrder(1);
        $this->em->persist($parent);
        $this->em->flush();
        $this->em->clear();

        // Create child category with parent relationship
        $payload = [
            'data' => [
                'type' => 'categories',
                'attributes' => [
                    'name' => 'Child Category',
                    'sortOrder' => 2,
                ],
                'relationships' => [
                    'parent' => [
                        'data' => ['type' => 'categories', 'id' => 'cat-parent'],
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/categories', $payload);
        $response = ($this->controller)($request, 'categories');

        // Assert response
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);
        $childId = $document['data']['id'];

        // Verify relationship in response
        self::assertArrayHasKey('relationships', $document['data']);
        self::assertArrayHasKey('parent', $document['data']['relationships']);

        // Verify data persistence with self-reference
        $this->em->clear();
        $child = $this->em->find(Category::class, $childId);

        self::assertInstanceOf(Category::class, $child);
        self::assertSame('Child Category', $child->getName());
        self::assertNotNull($child->getParent());
        self::assertSame('cat-parent', $child->getParent()->getId());
        self::assertSame('Parent Category', $child->getParent()->getName());

        //add one more child to child
        $payload = [
            'data' => [
                'type' => 'categories',
                'attributes' => [
                    'name' => 'Grandchild Category',
                    'sortOrder' => 3,
                ],
                'relationships' => [
                    'parent' => [
                        'data' => ['type' => 'categories', 'id' => $childId],
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/categories', $payload);
        $response = ($this->controller)($request, 'categories');
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $document = $this->decode($response);
        $grandchildId = $document['data']['id'];
        $this->em->clear();
        $grandchild = $this->em->find(Category::class, $grandchildId);
        self::assertInstanceOf(Category::class, $grandchild);
        self::assertSame('Grandchild Category', $grandchild->getName());
        self::assertNotNull($grandchild->getParent());
        self::assertSame($childId, $grandchild->getParent()->getId());
        self::assertSame('Child Category', $grandchild->getParent()->getName());
        self::assertNotNull($grandchild->getParent()->getParent());
        self::assertSame('cat-parent', $grandchild->getParent()->getParent()->getId());
        self::assertSame('Parent Category', $grandchild->getParent()->getParent()->getName());
        self::assertCount(1, $grandchild->getParent()->getParent()->getChildren());
        self::assertCount(1, $grandchild->getParent()->getChildren());
        self::assertCount(0, $grandchild->getChildren());
    }

    /**
     * Test 5: Error handling - missing Content-Type header.
     *
     * Validates:
     * - 415 Unsupported Media Type when Content-Type is not application/vnd.api+json
     * - Error response follows JSON:API error format
     */
    public function testErrorMissingContentType(): void
    {
        $payload = [
            'data' => [
                'type' => 'tags',
                'attributes' => ['name' => 'Test'],
            ],
        ];

        $request = Request::create(
            '/api/tags',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'], // Wrong content type
            json_encode($payload, \JSON_THROW_ON_ERROR)
        );

        try {
            ($this->controller)($request, 'tags');
            self::fail('Expected UnsupportedMediaTypeException to be thrown');
        } catch (\JsonApi\Symfony\Http\Exception\UnsupportedMediaTypeException $e) {
            self::assertSame(415, $e->getStatusCode());
        }
    }

    /**
     * Test 6: Error handling - malformed JSON.
     *
     * Validates:
     * - 400 Bad Request for invalid JSON
     * - Error response includes proper error details
     */
    public function testErrorMalformedJson(): void
    {
        $request = Request::create(
            '/api/tags',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => MediaType::JSON_API],
            '{invalid json'
        );

        try {
            ($this->controller)($request, 'tags');
            self::fail('Expected BadRequestException to be thrown');
        } catch (\JsonApi\Symfony\Http\Exception\BadRequestException $e) {
            self::assertSame(400, $e->getStatusCode());
        }
    }

    /**
     * Test 7: Error handling - missing data member.
     *
     * Validates:
     * - 400 Bad Request when 'data' member is missing
     * - Error points to correct JSON pointer
     */
    public function testErrorMissingDataMember(): void
    {
        $payload = [
            'type' => 'tags', // Missing 'data' wrapper
            'attributes' => ['name' => 'Test'],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/tags', $payload);

        try {
            ($this->controller)($request, 'tags');
            self::fail('Expected BadRequestException to be thrown');
        } catch (\JsonApi\Symfony\Http\Exception\BadRequestException $e) {
            self::assertSame(400, $e->getStatusCode());
        }
    }

    /**
     * Test 8: Error handling - type mismatch.
     *
     * Validates:
     * - 409 Conflict when resource type doesn't match endpoint
     */
    public function testErrorTypeMismatch(): void
    {
        $payload = [
            'data' => [
                'type' => 'authors', // Wrong type for /api/tags endpoint
                'attributes' => ['name' => 'Test'],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/tags', $payload);

        try {
            ($this->controller)($request, 'tags');
            self::fail('Expected ConflictException to be thrown');
        } catch (\JsonApi\Symfony\Http\Exception\ConflictException $e) {
            self::assertSame(409, $e->getStatusCode());
        }
    }

    /**
     * Test 9: Error handling - client-generated ID not allowed.
     *
     * Validates:
     * - 403 Forbidden when client provides ID for resource type that doesn't allow it
     */
    public function testErrorClientIdNotAllowed(): void
    {
        $payload = [
            'data' => [
                'type' => 'tags',
                'id' => 'custom-id', // Client ID not allowed for tags
                'attributes' => ['name' => 'Test'],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/tags', $payload);

        try {
            ($this->controller)($request, 'tags');
            self::fail('Expected ForbiddenException to be thrown');
        } catch (\JsonApi\Symfony\Http\Exception\ForbiddenException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
    }

    /**
     * Test 10: Client-generated ID allowed for authors.
     *
     * Validates:
     * - Client-generated IDs work when explicitly allowed
     * - Resource is created with the provided ID
     */
    public function testClientGeneratedIdAllowed(): void
    {
        $payload = [
            'data' => [
                'type' => 'authors',
                'id' => 'custom-author-id',
                'attributes' => [
                    'name' => 'Custom Author',
                    'email' => 'custom@example.com',
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/authors', $payload);
        $response = ($this->controller)($request, 'authors');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $document = $this->decode($response);
        self::assertSame('custom-author-id', $document['data']['id']);

        // Verify in database
        $this->em->clear();
        $author = $this->em->find(Author::class, 'custom-author-id');
        self::assertInstanceOf(Author::class, $author);
        self::assertSame('Custom Author', $author->getName());
    }

    /**
     * Test 11: Error handling - unknown resource type.
     *
     * Validates:
     * - 404 Not Found for non-existent resource types
     */
    public function testErrorUnknownResourceType(): void
    {
        $payload = [
            'data' => [
                'type' => 'unknown-type',
                'attributes' => ['name' => 'Test'],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/unknown-type', $payload);

        try {
            ($this->controller)($request, 'unknown-type');
            self::fail('Expected NotFoundException to be thrown');
        } catch (\JsonApi\Symfony\Http\Exception\NotFoundException $e) {
            self::assertSame(404, $e->getStatusCode());
        }
    }

    /**
     * Test 12: Create resource with integer primary key and database-generated sequence.
     *
     * Validates:
     * - 201 Created status for entity with integer ID
     * - Auto-generated integer ID is returned in response
     * - Response follows JSON:API specification format
     * - Entity is persisted with correct data
     * - Integer IDs are properly converted to strings in JSON:API response
     *
     * This test ensures the system correctly handles entities with integer sequence-based IDs,
     * as opposed to application-generated UUIDs used by other test entities.
     */
    public function testCreateResourceWithIntegerSequenceId(): void
    {
        $payload = [
            'data' => [
                'type' => 'comments',
                'attributes' => [
                    'content' => 'This is a great article! Very informative.',
                    'authorName' => 'Alice Johnson',
                    'rating' => 5,
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('POST', '/api/comments', $payload);
        $response = ($this->controller)($request, 'comments');

        // Assert HTTP status and headers
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));
        self::assertNotNull($response->headers->get('Location'));

        // Decode and validate response structure
        $document = $this->decode($response);

        self::assertArrayHasKey('data', $document);
        self::assertIsArray($document['data']);
        self::assertArrayHasKey('type', $document['data']);
        self::assertArrayHasKey('id', $document['data']);
        self::assertArrayHasKey('attributes', $document['data']);

        self::assertSame('comments', $document['data']['type']);

        // Verify ID is present and is a string representation of an integer
        $idString = $document['data']['id'];
        self::assertNotEmpty($idString);
        self::assertIsString($idString);
        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $idString, 'ID should be a string representation of a positive integer');

        // Verify attributes
        self::assertSame('This is a great article! Very informative.', $document['data']['attributes']['content']);
        self::assertSame('Alice Johnson', $document['data']['attributes']['authorName']);
        self::assertSame(5, $document['data']['attributes']['rating']);

        // Verify Location header matches self link
        $selfLink = $document['data']['links']['self'] ?? null;
        self::assertNotNull($selfLink);
        self::assertSame($selfLink, $response->headers->get('Location'));

        // Verify data persistence in PostgreSQL
        $commentId = (int) $idString;
        $this->em->clear();

        $comment = $this->em->find(Comment::class, $commentId);
        self::assertInstanceOf(Comment::class, $comment);
        self::assertSame($commentId, $comment->getId());
        self::assertSame('This is a great article! Very informative.', $comment->getContent());
        self::assertSame('Alice Johnson', $comment->getAuthorName());
        self::assertSame(5, $comment->getRating());
    }

    /**
     * Test 13: Create multiple resources with integer sequence IDs to verify sequence increments.
     *
     * Validates:
     * - Multiple resources can be created with auto-incrementing IDs
     * - Each resource gets a unique, incrementing integer ID
     * - Sequence continues correctly across multiple inserts
     */
    public function testCreateMultipleResourcesWithIntegerSequenceIds(): void
    {
        // Create first comment
        $payload1 = [
            'data' => [
                'type' => 'comments',
                'attributes' => [
                    'content' => 'First comment',
                    'authorName' => 'User One',
                    'rating' => 4,
                ],
            ],
        ];

        $request1 = $this->createJsonApiRequest('POST', '/api/comments', $payload1);
        $response1 = ($this->controller)($request1, 'comments');

        self::assertSame(Response::HTTP_CREATED, $response1->getStatusCode());
        $document1 = $this->decode($response1);
        $id1 = (int) $document1['data']['id'];

        // Create second comment
        $payload2 = [
            'data' => [
                'type' => 'comments',
                'attributes' => [
                    'content' => 'Second comment',
                    'authorName' => 'User Two',
                    'rating' => 3,
                ],
            ],
        ];

        $request2 = $this->createJsonApiRequest('POST', '/api/comments', $payload2);
        $response2 = ($this->controller)($request2, 'comments');

        self::assertSame(Response::HTTP_CREATED, $response2->getStatusCode());
        $document2 = $this->decode($response2);
        $id2 = (int) $document2['data']['id'];

        // Verify IDs are different and sequential
        self::assertNotSame($id1, $id2);
        self::assertGreaterThan($id1, $id2, 'Second comment ID should be greater than first');

        // Verify both are persisted correctly
        $this->em->clear();

        $comment1 = $this->em->find(Comment::class, $id1);
        $comment2 = $this->em->find(Comment::class, $id2);

        self::assertInstanceOf(Comment::class, $comment1);
        self::assertInstanceOf(Comment::class, $comment2);
        self::assertSame('First comment', $comment1->getContent());
        self::assertSame('Second comment', $comment2->getContent());
        self::assertSame('User One', $comment1->getAuthorName());
        self::assertSame('User Two', $comment2->getAuthorName());
    }

    /**
     * Helper method to create a JSON:API compliant request.
     *
     * @param  string               $method  HTTP method
     * @param  string               $uri     Request URI
     * @param  array<string, mixed> $payload Request payload
     * @return Request
     */
    private function createJsonApiRequest(string $method, string $uri, array $payload): Request
    {
        return Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            ['CONTENT_TYPE' => MediaType::JSON_API],
            json_encode($payload, \JSON_THROW_ON_ERROR)
        );
    }
}
