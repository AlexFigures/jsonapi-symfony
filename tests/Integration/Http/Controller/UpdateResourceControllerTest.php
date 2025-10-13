<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\Symfony\Filter\Operator\BetweenOperator;
use AlexFigures\Symfony\Filter\Operator\EqualOperator;
use AlexFigures\Symfony\Filter\Operator\GreaterOrEqualOperator;
use AlexFigures\Symfony\Filter\Operator\GreaterThanOperator;
use AlexFigures\Symfony\Filter\Operator\InOperator;
use AlexFigures\Symfony\Filter\Operator\IsNullOperator;
use AlexFigures\Symfony\Filter\Operator\LessOrEqualOperator;
use AlexFigures\Symfony\Filter\Operator\LessThanOperator;
use AlexFigures\Symfony\Filter\Operator\LikeOperator;
use AlexFigures\Symfony\Filter\Operator\NotEqualOperator;
use AlexFigures\Symfony\Filter\Operator\NotInOperator;
use AlexFigures\Symfony\Filter\Operator\Registry;
use AlexFigures\Symfony\Http\Controller\UpdateResourceController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration tests for UpdateResourceController.
 *
 * Tests PATCH /api/{type}/{id} endpoint with real PostgreSQL database.
 */
final class UpdateResourceControllerTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private UpdateResourceController $controller;

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

        // Set up routing (identical to CreateResourceControllerTest)
        $routes = new RouteCollection();
        $routes->add('jsonapi.resource', new Route('/api/{type}/{id}'));
        $routes->add('jsonapi.collection', new Route('/api/{type}'));
        $routes->add('jsonapi.relationship', new Route('/api/{type}/{id}/relationships/{relationship}'));
        $routes->add('jsonapi.related', new Route('/api/{type}/{id}/{relationship}'));

        // Add type-specific routes for LinkGenerator
        foreach (['articles', 'authors', 'tags', 'categories'] as $type) {
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

        // Set up error handling
        $errorBuilder = new \AlexFigures\Symfony\Http\Error\ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);

        // Set up operator registry with all standard operators
        $operatorRegistry = new Registry([
            new EqualOperator(),
            new NotEqualOperator(),
            new LessThanOperator(),
            new LessOrEqualOperator(),
            new GreaterThanOperator(),
            new GreaterOrEqualOperator(),
            new LikeOperator(),
            new InOperator(),
            new NotInOperator(),
            new BetweenOperator(),
            new IsNullOperator(),
        ]);

        // Set up filter handler registry
        $filterHandlerRegistry = new FilterHandlerRegistry();

        // Set up Doctrine filter compiler
        $filterCompiler = new DoctrineFilterCompiler($operatorRegistry, $filterHandlerRegistry);

        // Set up sort handler registry
        $sortHandlerRegistry = new SortHandlerRegistry();

        // Set up GenericDoctrineRepository
        $repository = new GenericDoctrineRepository(
            $this->em,
            $this->registry,
            $filterCompiler,
            $sortHandlerRegistry
        );

        // Set up LinkGenerator
        $linkGenerator = new \AlexFigures\Symfony\Http\Link\LinkGenerator($urlGenerator);

        // Set up DocumentBuilder
        $documentBuilder = new DocumentBuilder(
            $this->registry,
            $this->accessor,
            $linkGenerator,
            'always'
        );

        // Set up write configuration (allow relationships, no client IDs for updates)
        $writeConfig = new \AlexFigures\Symfony\Http\Write\WriteConfig(true, []);

        // Set up InputDocumentValidator
        $inputValidator = new InputDocumentValidator($this->registry, $writeConfig, $errorMapper);

        // Set up ChangeSetFactory
        $changeSetFactory = new ChangeSetFactory($this->registry);

        // Set up ConstraintViolationMapper
        $violationMapper = new ConstraintViolationMapper($this->registry, $errorMapper);

        // Set up EventDispatcher
        $eventDispatcher = new EventDispatcher();

        // Create UpdateResourceController
        $this->controller = new UpdateResourceController(
            $this->registry,
            $inputValidator,
            $changeSetFactory,
            $this->validatingProcessor,
            $this->transactionManager,
            $documentBuilder,
            $errorMapper,
            $violationMapper,
            $eventDispatcher
        );
    }

    /**
     * Test 1: Update simple resource attributes (no relationships).
     *
     * Validates:
     * - 200 OK status
     * - Updated attributes in response
     * - Data persisted in database
     */
    public function testUpdateSimpleResourceAttributes(): void
    {
        // Create initial tag
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();
        $this->em->clear();

        // Update tag name
        $payload = [
            'data' => [
                'type' => 'tags',
                'id' => $tagId,
                'attributes' => [
                    'name' => 'PHP 8.4',
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/tags/{$tagId}", $payload);
        $response = ($this->controller)($request, 'tags', $tagId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API, $response->headers->get('Content-Type'));

        $document = $this->decode($response);

        self::assertSame('tags', $document['data']['type']);
        self::assertSame($tagId, $document['data']['id']);
        self::assertSame('PHP 8.4', $document['data']['attributes']['name']);

        // Verify persistence
        $this->em->clear();
        $updatedTag = $this->em->find(Tag::class, $tagId);
        self::assertNotNull($updatedTag);
        self::assertSame('PHP 8.4', $updatedTag->getName());
    }

    /**
     * Test 2: Update resource with to-one relationship.
     *
     * Validates:
     * - Relationship can be updated
     * - Response includes updated relationship
     */
    public function testUpdateResourceWithToOneRelationship(): void
    {
        // Create authors
        $author1 = new Author();
        $author1->setName('John Doe');
        $author1->setEmail('john@example.com');
        $this->em->persist($author1);

        $author2 = new Author();
        $author2->setName('Jane Smith');
        $author2->setEmail('jane@example.com');
        $this->em->persist($author2);

        // Create article with author1
        $article = new Article();
        $article->setTitle('Original Title');
        $article->setContent('Original content');
        $article->setAuthor($author1);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $author2Id = $author2->getId();
        $this->em->clear();

        // Update article to use author2
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => $articleId,
                'attributes' => [
                    'title' => 'Updated Title',
                ],
                'relationships' => [
                    'author' => [
                        'data' => ['type' => 'authors', 'id' => $author2Id],
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/articles/{$articleId}", $payload);
        $response = ($this->controller)($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertSame('Updated Title', $document['data']['attributes']['title']);
        self::assertSame($author2Id, $document['data']['relationships']['author']['data']['id']);

        // Verify persistence
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertSame('Updated Title', $updatedArticle->getTitle());
        self::assertSame($author2Id, $updatedArticle->getAuthor()->getId());
    }

    /**
     * Test 3: Update resource with to-many relationship (replace all).
     *
     * Validates:
     * - To-many relationships can be replaced
     * - All old relationships removed, new ones added
     */
    public function testUpdateResourceWithToManyRelationship(): void
    {
        // Create tags
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        $tag3 = new Tag();
        $tag3->setName('Doctrine');
        $this->em->persist($tag3);

        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article with tag1 and tag2
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $article->addTag($tag1);
        $article->addTag($tag2);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $tag2Id = $tag2->getId();
        $tag3Id = $tag3->getId();
        $this->em->clear();

        // Update article to have only tag2 and tag3
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => $articleId,
                'relationships' => [
                    'tags' => [
                        'data' => [
                            ['type' => 'tags', 'id' => $tag2Id],
                            ['type' => 'tags', 'id' => $tag3Id],
                        ],
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/articles/{$articleId}", $payload);
        $response = ($this->controller)($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Verify tags in response
        self::assertCount(2, $document['data']['relationships']['tags']['data']);
        $tagIds = array_column($document['data']['relationships']['tags']['data'], 'id');
        self::assertContains($tag2Id, $tagIds);
        self::assertContains($tag3Id, $tagIds);

        // Verify persistence
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertCount(2, $updatedArticle->getTags());
    }

    /**
     * Test 4: Update resource - clear to-one relationship (set to null).
     *
     * Validates:
     * - Relationship can be set to null
     * - Response shows null relationship
     */
    public function testUpdateResourceClearToOneRelationship(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article with author
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        // Clear author relationship
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => $articleId,
                'relationships' => [
                    'author' => [
                        'data' => null,
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/articles/{$articleId}", $payload);
        $response = ($this->controller)($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertNull($document['data']['relationships']['author']['data']);

        // Verify persistence
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertNull($updatedArticle->getAuthor());
    }

    /**
     * Test 5: Error - resource not found (404).
     *
     * Validates:
     * - 404 status when resource doesn't exist
     */
    public function testErrorResourceNotFound(): void
    {
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        $payload = [
            'data' => [
                'type' => 'tags',
                'id' => $nonExistentId,
                'attributes' => [
                    'name' => 'New Name',
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/tags/{$nonExistentId}", $payload);

        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);

        ($this->controller)($request, 'tags', $nonExistentId);
    }

    /**
     * Test 6: Error - unknown resource type (404).
     *
     * Validates:
     * - 404 status for unknown type
     */
    public function testErrorUnknownResourceType(): void
    {
        $payload = [
            'data' => [
                'type' => 'unknown-type',
                'id' => 'some-id',
                'attributes' => [],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', '/api/unknown-type/some-id', $payload);

        $this->expectException(\AlexFigures\Symfony\Http\Exception\NotFoundException::class);
        $this->expectExceptionMessage('Resource type "unknown-type" not found');

        ($this->controller)($request, 'unknown-type', 'some-id');
    }

    /**
     * Test 7: Missing Content-Type header is allowed.
     *
     * Validates:
     * - Request succeeds even without Content-Type header
     */
    public function testMissingContentTypeIsAllowed(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();
        $this->em->clear();

        $payload = [
            'data' => [
                'type' => 'tags',
                'id' => $tagId,
                'attributes' => [
                    'name' => 'Updated',
                ],
            ],
        ];

        $request = Request::create(
            "/api/tags/{$tagId}",
            'PATCH',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => MediaType::JSON_API],
            json_encode($payload)
        );

        $response = ($this->controller)($request, 'tags', $tagId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        self::assertSame('Updated', $document['data']['attributes']['name']);
    }

    /**
     * Test 8: Error - invalid Content-Type (415).
     *
     * Validates:
     * - 415 status for wrong Content-Type
     */
    public function testErrorInvalidContentType(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();

        $payload = [
            'data' => [
                'type' => 'tags',
                'id' => $tagId,
                'attributes' => [
                    'name' => 'Updated',
                ],
            ],
        ];

        $request = Request::create(
            "/api/tags/{$tagId}",
            'PATCH',
            [],
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => MediaType::JSON_API,
            ],
            json_encode($payload)
        );

        $this->expectException(\AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException::class);

        ($this->controller)($request, 'tags', $tagId);
    }

    /**
     * Test 9: Error - malformed JSON (400).
     *
     * Validates:
     * - 400 status for invalid JSON
     */
    public function testErrorMalformedJson(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();

        $request = Request::create(
            "/api/tags/{$tagId}",
            'PATCH',
            [],
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => MediaType::JSON_API,
                'HTTP_ACCEPT' => MediaType::JSON_API,
            ],
            '{invalid json'
        );

        $this->expectException(\AlexFigures\Symfony\Http\Exception\BadRequestException::class);
        $this->expectExceptionMessage('Malformed JSON');

        ($this->controller)($request, 'tags', $tagId);
    }

    /**
     * Test 10: Error - empty request body (400).
     *
     * Validates:
     * - 400 status for empty body
     */
    public function testErrorEmptyRequestBody(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();

        $request = Request::create(
            "/api/tags/{$tagId}",
            'PATCH',
            [],
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => MediaType::JSON_API,
                'HTTP_ACCEPT' => MediaType::JSON_API,
            ],
            ''
        );

        $this->expectException(\AlexFigures\Symfony\Http\Exception\BadRequestException::class);
        $this->expectExceptionMessage('Request body must not be empty');

        ($this->controller)($request, 'tags', $tagId);
    }

    /**
     * Test 11: Error - missing data member (400).
     *
     * Validates:
     * - 400 status when 'data' is missing
     */
    public function testErrorMissingDataMember(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();

        $payload = [
            'attributes' => [
                'name' => 'Updated',
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/tags/{$tagId}", $payload);

        $this->expectException(\AlexFigures\Symfony\Http\Exception\BadRequestException::class);

        ($this->controller)($request, 'tags', $tagId);
    }

    /**
     * Test 12: Error - type mismatch (409).
     *
     * Validates:
     * - 409 status when type in payload doesn't match URL
     */
    public function testErrorTypeMismatch(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();

        $payload = [
            'data' => [
                'type' => 'articles',  // Wrong type
                'id' => $tagId,
                'attributes' => [
                    'name' => 'Updated',
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/tags/{$tagId}", $payload);

        $this->expectException(\AlexFigures\Symfony\Http\Exception\ConflictException::class);

        ($this->controller)($request, 'tags', $tagId);
    }

    /**
     * Test 13: Error - ID mismatch (409).
     *
     * Validates:
     * - 409 status when ID in payload doesn't match URL
     */
    public function testErrorIdMismatch(): void
    {
        $tag = new Tag();
        $tag->setName('PHP');
        $this->em->persist($tag);
        $this->em->flush();

        $tagId = $tag->getId();
        $wrongId = '00000000-0000-0000-0000-000000000000';

        $payload = [
            'data' => [
                'type' => 'tags',
                'id' => $wrongId,  // Wrong ID
                'attributes' => [
                    'name' => 'Updated',
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/tags/{$tagId}", $payload);

        $this->expectException(\AlexFigures\Symfony\Http\Exception\ConflictException::class);

        ($this->controller)($request, 'tags', $tagId);
    }

    /**
     * Test 14: Partial update - only attributes.
     *
     * Validates:
     * - Can update only attributes without touching relationships
     */
    public function testPartialUpdateOnlyAttributes(): void
    {
        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article with author
        $article = new Article();
        $article->setTitle('Original Title');
        $article->setContent('Original Content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $authorId = $author->getId();
        $this->em->clear();

        // Update only title (not content or author)
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => $articleId,
                'attributes' => [
                    'title' => 'Updated Title',
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/articles/{$articleId}", $payload);
        $response = ($this->controller)($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertSame('Updated Title', $document['data']['attributes']['title']);
        self::assertSame('Original Content', $document['data']['attributes']['content']);
        self::assertSame($authorId, $document['data']['relationships']['author']['data']['id']);

        // Verify persistence
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertSame('Updated Title', $updatedArticle->getTitle());
        self::assertSame('Original Content', $updatedArticle->getContent());
        self::assertSame($authorId, $updatedArticle->getAuthor()->getId());
    }

    /**
     * Test 15: Partial update - only relationships.
     *
     * Validates:
     * - Can update only relationships without touching attributes
     */
    public function testPartialUpdateOnlyRelationships(): void
    {
        // Create tags
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article with tag1
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Test Content');
        $article->setAuthor($author);
        $article->addTag($tag1);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $tag2Id = $tag2->getId();
        $this->em->clear();

        // Update only tags (not title or content)
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => $articleId,
                'relationships' => [
                    'tags' => [
                        'data' => [
                            ['type' => 'tags', 'id' => $tag2Id],
                        ],
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/articles/{$articleId}", $payload);
        $response = ($this->controller)($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertSame('Test Article', $document['data']['attributes']['title']);
        self::assertSame('Test Content', $document['data']['attributes']['content']);
        self::assertCount(1, $document['data']['relationships']['tags']['data']);
        self::assertSame($tag2Id, $document['data']['relationships']['tags']['data'][0]['id']);

        // Verify persistence
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertSame('Test Article', $updatedArticle->getTitle());
        self::assertCount(1, $updatedArticle->getTags());
    }

    /**
     * Test 16: Update with empty to-many relationship (clear all).
     *
     * Validates:
     * - Can clear all to-many relationships by passing empty array
     */
    public function testUpdateClearToManyRelationship(): void
    {
        // Create tags
        $tag1 = new Tag();
        $tag1->setName('PHP');
        $this->em->persist($tag1);

        $tag2 = new Tag();
        $tag2->setName('Symfony');
        $this->em->persist($tag2);

        // Create author
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        // Create article with tags
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $article->addTag($tag1);
        $article->addTag($tag2);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        // Clear all tags
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => $articleId,
                'relationships' => [
                    'tags' => [
                        'data' => [],
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/articles/{$articleId}", $payload);
        $response = ($this->controller)($request, 'articles', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        self::assertCount(0, $document['data']['relationships']['tags']['data']);

        // Verify persistence
        $this->em->clear();
        $updatedArticle = $this->em->find(Article::class, $articleId);
        self::assertNotNull($updatedArticle);
        self::assertCount(0, $updatedArticle->getTags());
    }

    /**
     * Helper method to create JSON:API request.
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
            [
                'HTTP_CONTENT_TYPE' => MediaType::JSON_API,
                'HTTP_ACCEPT' => MediaType::JSON_API,
            ],
            json_encode($payload)
        );
    }
}
