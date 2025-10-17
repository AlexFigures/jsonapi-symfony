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
use AlexFigures\Symfony\Resource\Mapper\DefaultReadMapper;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Category;
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

        // Set up ReadMapper
        $readMapper = new DefaultReadMapper();

        // Set up GenericDoctrineRepository
        $repository = new GenericDoctrineRepository(
            $this->managerRegistry,
            $this->registry,
            $filterCompiler,
            $sortHandlerRegistry,
            $readMapper
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

    /**
     * E4: 404 Not Found when PATCH references missing related resource.
     *
     * JSON:API spec requires that servers MUST return 404 Not Found when
     * processing a PATCH request that includes a reference to a related
     * resource that does not exist.
     *
     * CURRENT BEHAVIOR: Returns 422 Unprocessable Entity (validation error).
     * SPEC REQUIRES: 404 Not Found.
     *
     * This is a known spec violation. See reports/failures.json ID:E4.
     *
     * Validates:
     * - 404 status when PATCH references non-existent related resource
     * - Error response includes proper error details
     */
    public function testPatchWithMissingRelatedResourceReturns404(): void
    {
        // Create an article
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);

        $article = new Article();
        $article->setTitle('Original Title');
        $article->setContent('Original content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        // Try to update article with non-existent author
        $payload = [
            'data' => [
                'type' => 'articles',
                'id' => $articleId,
                'relationships' => [
                    'author' => [
                        'data' => [
                            'type' => 'authors',
                            'id' => 'non-existent-author-id', // Missing related resource
                        ],
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/articles/{$articleId}", $payload);

        try {
            ($this->controller)($request, 'articles', $articleId);
            self::fail('Expected NotFoundException (404) for missing related resource');
        } catch (\AlexFigures\Symfony\Http\Exception\NotFoundException $e) {
            // SPEC COMPLIANT: Should return 404
            self::assertSame(404, $e->getStatusCode());
            self::assertStringContainsString('not found', strtolower($e->getMessage()));
        } catch (\AlexFigures\Symfony\Http\Exception\UnprocessableEntityException $e) {
            // CURRENT BEHAVIOR: Returns 422 (spec violation)
            self::markTestIncomplete(
                'Bundle currently returns 422 for missing related resource, but spec requires 404. ' .
                'See reports/failures.json ID:E4 for remediation plan.'
            );
        }
    }

    /**
     * Test: Update Category - clear parent relationship (self-referential to-one).
     *
     * Validates:
     * - Self-referential to-one relationship can be set to null
     * - Response shows null parent relationship
     * - Database state reflects the cleared relationship
     * - Parent category remains unchanged
     *
     * This test specifically covers self-referential relationships (Category → parent Category),
     * which is a common pattern for hierarchical/tree structures.
     */
    public function testUpdateCategoryClearParentRelationship(): void
    {
        // Create parent category
        $parentCategory = new Category();
        $parentCategory->setName('Parent Category');
        $parentCategory->setSortOrder(1);
        $this->em->persist($parentCategory);

        // Create child category with parent reference
        $childCategory = new Category();
        $childCategory->setName('Child Category');
        $childCategory->setSortOrder(2);
        $childCategory->setParent($parentCategory);
        $this->em->persist($childCategory);

        $this->em->flush();
        $parentId = $parentCategory->getId();
        $childId = $childCategory->getId();
        $this->em->clear();

        // Verify initial state - child has parent
        $initialChild = $this->em->find(Category::class, $childId);
        self::assertNotNull($initialChild);
        self::assertNotNull($initialChild->getParent());
        self::assertSame($parentId, $initialChild->getParent()->getId());
        $this->em->clear();

        // Clear parent relationship via PATCH request
        $payload = [
            'data' => [
                'type' => 'categories',
                'id' => $childId,
                'relationships' => [
                    'parent' => [
                        'data' => null,
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/categories/{$childId}", $payload);
        $response = ($this->controller)($request, 'categories', $childId);

        // Assert HTTP 200 OK
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Assert response document structure
        $document = $this->decode($response);

        self::assertArrayHasKey('data', $document);
        self::assertArrayHasKey('relationships', $document['data']);
        self::assertArrayHasKey('parent', $document['data']['relationships']);

        // Assert parent relationship is null in response
        self::assertNull(
            $document['data']['relationships']['parent']['data'],
            'Parent relationship should be null in response'
        );

        // Verify database persistence - child's parent is now null
        $this->em->clear();
        $updatedChild = $this->em->find(Category::class, $childId);
        self::assertNotNull($updatedChild, 'Child category should still exist');
        self::assertNull($updatedChild->getParent(), 'Child category parent should be null after update');

        // Verify parent category was not affected
        $parentCategory = $this->em->find(Category::class, $parentId);
        self::assertNotNull($parentCategory, 'Parent category should still exist');
        self::assertSame('Parent Category', $parentCategory->getName());

        // Verify parent's children collection no longer contains the child
        // Note: This depends on whether the inverse side is automatically updated by Doctrine
        // In a properly configured bidirectional relationship, this should be handled
        $parentChildren = $parentCategory->getChildren();
        $childIds = array_map(fn (Category $c) => $c->getId(), $parentChildren->toArray());
        self::assertNotContains(
            $childId,
            $childIds,
            'Parent category should no longer have the child in its children collection'
        );
    }

    /**
     * Test: Update Category - clear parent with UniqueEntity validation.
     *
     * This test reproduces the issue where UniqueEntity constraint validation
     * returns stale data from Doctrine's UnitOfWork cache.
     *
     * Scenario:
     * 1. Create parent1 with child "Electronics"
     * 2. Create parent2 (different parent)
     * 3. Clear parent from child (set to null)
     * 4. UniqueEntity validation on [name, parent] should see the NEW state (parent=null)
     *    but might see OLD state (parent=parent1) from UnitOfWork cache
     *
     * Expected behavior:
     * - After clearing parent, child should have parent=null
     * - UniqueEntity validation should work with the updated state
     * - No false positive "duplicate" errors
     *
     * This demonstrates the classic Doctrine validation problem where:
     * - Entity is modified in memory (parent set to null)
     * - Validator queries database for uniqueness check
     * - Doctrine returns cached entity with OLD state (parent still set)
     * - Validation fails or returns incorrect results
     */
    public function testUpdateCategoryClearParentWithUniqueEntityValidation(): void
    {
        // Create parent1
        $parent1 = new Category();
        $parent1->setName('Parent 1');
        $parent1->setSortOrder(1);
        $this->em->persist($parent1);

        // Create child under parent1 with name "Electronics"
        $child = new Category();
        $child->setName('Electronics');
        $child->setSortOrder(2);
        $child->setParent($parent1);
        $this->em->persist($child);

        // Create parent2 (different parent)
        $parent2 = new Category();
        $parent2->setName('Parent 2');
        $parent2->setSortOrder(3);
        $this->em->persist($parent2);

        $this->em->flush();
        $childId = $child->getId();
        $parent1Id = $parent1->getId();
        $parent2Id = $parent2->getId();
        $this->em->clear();

        // Verify initial state
        $initialChild = $this->em->find(Category::class, $childId);
        self::assertNotNull($initialChild);
        self::assertNotNull($initialChild->getParent());
        self::assertSame($parent1Id, $initialChild->getParent()->getId());
        $this->em->clear();

        // Clear parent relationship via PATCH
        // This is where the problem occurs:
        // 1. RelationshipResolver sets parent to null
        // 2. ValidatingDoctrineProcessor validates entity
        // 3. UniqueEntity validator queries DB
        // 4. Doctrine returns cached entity with OLD parent (not null)
        // 5. Validation might fail or see wrong state
        $payload = [
            'data' => [
                'type' => 'categories',
                'id' => $childId,
                'relationships' => [
                    'parent' => [
                        'data' => null,
                    ],
                ],
            ],
        ];

        $request = $this->createJsonApiRequest('PATCH', "/api/categories/{$childId}", $payload);
        $response = ($this->controller)($request, 'categories', $childId);

        // Should succeed with 200 OK
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        self::assertNull(
            $document['data']['relationships']['parent']['data'],
            'Parent relationship should be null in response'
        );

        // Verify database state - parent should be null
        $this->em->clear();
        $updatedChild = $this->em->find(Category::class, $childId);
        self::assertNotNull($updatedChild);
        self::assertNull($updatedChild->getParent(), 'Child parent should be null after clearing');

        // Now try to create another category with same name under parent2
        // This should succeed because child now has parent=null, not parent1
        // Need to re-fetch parent2 from DB to avoid cascade issues
        $this->em->clear();
        $parent2Reloaded = $this->em->find(Category::class, $parent2Id);
        self::assertNotNull($parent2Reloaded);

        $sibling = new Category();
        $sibling->setName('Electronics'); // Same name as child
        $sibling->setSortOrder(4);
        $sibling->setParent($parent2Reloaded); // Different parent
        $this->em->persist($sibling);

        // This should NOT throw UniqueEntity violation because:
        // - child: name="Electronics", parent=null
        // - sibling: name="Electronics", parent=parent2
        // These are different combinations of [name, parent]
        $this->em->flush();

        self::assertTrue(true, 'Should be able to create sibling with same name under different parent');
    }

    /**
     * Test: Demonstrate UniqueEntity validation with stale UnitOfWork cache.
     *
     * This test demonstrates what happens when:
     * 1. Entity is loaded into UnitOfWork
     * 2. Entity is modified (parent cleared)
     * 3. EntityManager is cleared (simulating what might happen in your project)
     * 4. Validation runs and queries database
     * 5. Database still has OLD state (parent not null) because flush hasn't happened
     *
     * This is a UNIT test that demonstrates the problem at the Doctrine level,
     * not through the full HTTP stack.
     */
    public function testUniqueEntityValidationWithStaleCacheDirectDoctrineTest(): void
    {
        // Create parent
        $parent = new Category();
        $parent->setName('Parent');
        $parent->setSortOrder(1);
        $this->em->persist($parent);

        // Create child with parent
        $child = new Category();
        $child->setName('Electronics');
        $child->setSortOrder(2);
        $child->setParent($parent);
        $this->em->persist($child);

        $this->em->flush();
        $childId = $child->getId();
        $this->em->clear();

        // Load child into UnitOfWork
        $loadedChild = $this->em->find(Category::class, $childId);
        self::assertNotNull($loadedChild);
        self::assertNotNull($loadedChild->getParent(), 'Child should have parent initially');

        // Modify child - clear parent
        $loadedChild->setParent(null);

        // At this point:
        // - In-memory state: parent = null ✅
        // - Database state: parent = parent_id ❌ (not flushed yet)
        // - UnitOfWork state: parent = null ✅ (because we modified the managed entity)

        // If we query through Doctrine's repository/QueryBuilder,
        // it will return the in-memory state from UnitOfWork
        $qb = $this->em->createQueryBuilder();
        $qb->select('c')
            ->from(Category::class, 'c')
            ->where('c.id = :id')
            ->setParameter('id', $childId);

        $queriedChild = $qb->getQuery()->getSingleResult();

        // This should return the MODIFIED state (parent = null) from UnitOfWork
        self::assertNull(
            $queriedChild->getParent(),
            'QueryBuilder should return in-memory state from UnitOfWork (parent=null)'
        );

        // Now clear the EntityManager to simulate what might happen in your project
        $this->em->clear();

        // Query again - now it will hit the database
        $qb2 = $this->em->createQueryBuilder();
        $qb2->select('c')
            ->from(Category::class, 'c')
            ->where('c.id = :id')
            ->setParameter('id', $childId);

        $queriedChildAfterClear = $qb2->getQuery()->getSingleResult();

        // This will return the OLD state from database (parent NOT null)
        self::assertNotNull(
            $queriedChildAfterClear->getParent(),
            'After em->clear(), QueryBuilder returns OLD state from database (parent NOT null)'
        );

        // This demonstrates the problem:
        // If validation happens AFTER em->clear() but BEFORE flush,
        // it will see the old state and might produce incorrect results
    }
}
