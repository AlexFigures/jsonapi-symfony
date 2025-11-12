<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\Symfony\Filter\Operator\EqualOperator;
use AlexFigures\Symfony\Filter\Operator\GreaterOrEqualOperator;
use AlexFigures\Symfony\Filter\Operator\GreaterThanOperator;
use AlexFigures\Symfony\Filter\Operator\ILikeOperator;
use AlexFigures\Symfony\Filter\Operator\InOperator;
use AlexFigures\Symfony\Filter\Operator\IsNullOperator;
use AlexFigures\Symfony\Filter\Operator\LessOrEqualOperator;
use AlexFigures\Symfony\Filter\Operator\LessThanOperator;
use AlexFigures\Symfony\Filter\Operator\LikeOperator;
use AlexFigures\Symfony\Filter\Operator\NotEqualOperator;
use AlexFigures\Symfony\Filter\Operator\NotInOperator;
use AlexFigures\Symfony\Filter\Operator\Registry;
use AlexFigures\Symfony\Filter\Parser\FilterParser;
use AlexFigures\Symfony\Http\Controller\CollectionController;
use AlexFigures\Symfony\Http\Controller\ResourceController;
use AlexFigures\Symfony\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\Symfony\Http\Controller\Support\OperationValidator;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Resource\Mapper\DefaultReadMapper;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ArticleWithSpecialTags;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ArticleWithSpecialTagsSpecialTag;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\AuthorForSpecialTags;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\SpecialTag;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration tests for propertyPath alias feature.
 *
 * Tests the ability to expose a relationship through a different path:
 * - API exposes: Article.specialTags (type: special-tags)
 * - Doctrine path: ArticleWithSpecialTags.articleSpecialTags.specialTag
 * - ArticleWithSpecialTagsSpecialTag is NOT a JSON:API resource
 */
final class PropertyPathAliasTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private CollectionController $collectionController;
    private ResourceController $resourceController;
    private LinkGenerator $linkGenerator;

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

        // Re-create registry with SpecialTag and ArticleWithSpecialTags included
        $this->registry = new \AlexFigures\Symfony\Resource\Registry\ResourceRegistry([
            Article::class,
            ArticleWithSpecialTags::class,  // NEW: Add ArticleWithSpecialTags for propertyPath tests
            Author::class,
            AuthorForSpecialTags::class,  // NEW: Add AuthorForSpecialTags for propertyPath tests
            \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Category::class,
            \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\CategorySynonym::class,
            \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Comment::class,
            \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag::class,
            \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Product::class,
            \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\TypeTestEntity::class,
            \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\User::class,
            SpecialTag::class,  // NEW: Add SpecialTag for propertyPath tests
        ]);

        // Set up routing for LinkGenerator
        $routes = new RouteCollection();
        $routes->add('jsonapi.collection', new Route('/api/{type}'));
        $routes->add('jsonapi.resource', new Route('/api/{type}/{id}'));

        // Add type-specific routes for LinkGenerator (including special-tags and articles-with-special-tags)
        foreach (['articles', 'authors', 'tags', 'special-tags', 'articles-with-special-tags'] as $type) {
            $routes->add("jsonapi.{$type}.index", new Route("/api/{$type}"));
            $routes->add("jsonapi.{$type}.show", new Route("/api/{$type}/{id}"));
        }

        // Add relationship routes for articles
        $routes->add("jsonapi.articles.related.author", new Route("/api/articles/{id}/author"));
        $routes->add("jsonapi.articles.relationships.author.show", new Route("/api/articles/{id}/relationships/author"));
        $routes->add("jsonapi.articles.related.tags", new Route("/api/articles/{id}/tags"));
        $routes->add("jsonapi.articles.relationships.tags.show", new Route("/api/articles/{id}/relationships/tags"));

        // Add relationship routes for articles-with-special-tags
        $routes->add("jsonapi.articles-with-special-tags.related.author", new Route("/api/articles-with-special-tags/{id}/author"));
        $routes->add("jsonapi.articles-with-special-tags.relationships.author.show", new Route("/api/articles-with-special-tags/{id}/relationships/author"));
        $routes->add("jsonapi.articles-with-special-tags.related.tags", new Route("/api/articles-with-special-tags/{id}/tags"));
        $routes->add("jsonapi.articles-with-special-tags.relationships.tags.show", new Route("/api/articles-with-special-tags/{id}/relationships/tags"));
        $routes->add("jsonapi.articles-with-special-tags.related.specialTags", new Route("/api/articles-with-special-tags/{id}/specialTags"));
        $routes->add("jsonapi.articles-with-special-tags.relationships.specialTags.show", new Route("/api/articles-with-special-tags/{id}/relationships/specialTags"));

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

        // Set up pagination configuration
        $paginationConfig = new PaginationConfig(defaultSize: 10, maxSize: 100);

        // Set up sorting whitelist
        $sortingWhitelist = new SortingWhitelist($this->registry);

        // Set up filtering whitelist
        $filteringWhitelist = new FilteringWhitelist($this->registry, $errorMapper);

        // Set up filter parser
        $filterParser = new FilterParser();

        // Set up QueryParser
        $queryParser = new QueryParser(
            $this->registry,
            $paginationConfig,
            $sortingWhitelist,
            $filteringWhitelist,
            $errorMapper,
            $filterParser
        );

        // Set up operator registry
        $operatorRegistry = new Registry([
            new EqualOperator(),
            new NotEqualOperator(),
            new LessThanOperator(),
            new LessOrEqualOperator(),
            new GreaterThanOperator(),
            new GreaterOrEqualOperator(),
            new LikeOperator(),
            new ILikeOperator(),
            new InOperator(),
            new NotInOperator(),
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
            $filterHandlerRegistry,
            $sortHandlerRegistry,
            $readMapper
        );

        $operationValidator = new OperationValidator($errorMapper);
        $responseFactory = new JsonApiResponseFactory();

        // Create controllers
        $this->collectionController = new CollectionController(
            $this->registry,
            $operationValidator,
            $responseFactory,
            $repository,
            $queryParser,
            $documentBuilder,
        );

        $this->resourceController = new ResourceController(
            $this->registry,
            $operationValidator,
            $responseFactory,
            $repository,
            $queryParser,
            $documentBuilder,
            $errorMapper,
        );
    }

    /**
     * Test 1: Filter by aliased relationship field.
     *
     * Validates:
     * - filter[specialTags.name]=value works
     * - Internally resolves to articleSpecialTags.specialTag.name
     * - Returns only matching articles
     */
    public function testFilterByAliasedRelationshipField(): void
    {
        // Create test data
        $author = new AuthorForSpecialTags();
        $author->setName('Test Author');
        $author->setEmail('author@example.com');
        $this->em->persist($author);

        $specialTag1 = new SpecialTag();
        $specialTag1->setName('PHP');
        $specialTag1->setCategory('language');
        $this->em->persist($specialTag1);

        $specialTag2 = new SpecialTag();
        $specialTag2->setName('Symfony');
        $specialTag2->setCategory('framework');
        $this->em->persist($specialTag2);

        $article1 = new ArticleWithSpecialTags();
        $article1->setTitle('Article with PHP tag');
        $article1->setContent('Content 1');
        $article1->setAuthor($author);
        $article1->addInternalSpecialTag($specialTag1);  // Use direct ManyToMany relationship
        $this->em->persist($article1);

        $article2 = new ArticleWithSpecialTags();
        $article2->setTitle('Article with Symfony tag');
        $article2->setContent('Content 2');
        $article2->setAuthor($author);
        $article2->addInternalSpecialTag($specialTag2);  // Use direct ManyToMany relationship
        $this->em->persist($article2);

        $this->em->flush();
        $this->em->clear();

        // Filter by specialTags.name (should resolve to articleSpecialTags.specialTag.name)
        $request = $this->createJsonApiGetRequest('GET', '/api/articles-with-special-tags?filter[specialTags.name]=PHP');
        $response = ($this->collectionController)($request, 'articles-with-special-tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Should return only article1
        self::assertCount(1, $document['data']);
        self::assertSame('Article with PHP tag', $document['data'][0]['attributes']['title']);
    }

    /**
     * Test 2: Sort by aliased relationship field.
     *
     * Validates:
     * - sort=specialTags.name works
     * - Internally resolves to articleSpecialTags.specialTag.name
     */
    public function testSortByAliasedRelationshipField(): void
    {
        // Create test data
        $author = new AuthorForSpecialTags();
        $author->setName('Test Author');
        $author->setEmail('author@example.com');
        $this->em->persist($author);

        $specialTagA = new SpecialTag();
        $specialTagA->setName('AAA Tag');
        $this->em->persist($specialTagA);

        $specialTagZ = new SpecialTag();
        $specialTagZ->setName('ZZZ Tag');
        $this->em->persist($specialTagZ);

        $article1 = new ArticleWithSpecialTags();
        $article1->setTitle('Article 1');
        $article1->setContent('Content 1');
        $article1->setAuthor($author);
        $article1->addInternalSpecialTag($specialTagZ);  // Use direct ManyToMany relationship
        $this->em->persist($article1);

        $article2 = new ArticleWithSpecialTags();
        $article2->setTitle('Article 2');
        $article2->setContent('Content 2');
        $article2->setAuthor($author);
        $article2->addInternalSpecialTag($specialTagA);  // Use direct ManyToMany relationship
        $this->em->persist($article2);

        $this->em->flush();
        $this->em->clear();

        // Sort by specialTags.name ascending
        $request = $this->createJsonApiGetRequest('GET', '/api/articles-with-special-tags?sort=specialTags.name');
        $response = ($this->collectionController)($request, 'articles-with-special-tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Should be sorted: Article 2 (AAA), Article 1 (ZZZ)
        self::assertCount(2, $document['data']);
        self::assertSame('Article 2', $document['data'][0]['attributes']['title']);
        self::assertSame('Article 1', $document['data'][1]['attributes']['title']);
    }

    /**
     * Test 3: Include aliased relationship.
     *
     * Validates:
     * - include=specialTags works
     * - Internally resolves to articleSpecialTags.specialTag
     * - Returns SpecialTag resources in included section
     * - ArticleWithSpecialTagsSpecialTag is NOT included (it's not a resource)
     */
    public function testIncludeAliasedRelationship(): void
    {
        // Create test data
        $author = new AuthorForSpecialTags();
        $author->setName('Test Author');
        $author->setEmail('author@example.com');
        $this->em->persist($author);

        $specialTag1 = new SpecialTag();
        $specialTag1->setName('PHP');
        $specialTag1->setCategory('language');
        $this->em->persist($specialTag1);

        $specialTag2 = new SpecialTag();
        $specialTag2->setName('Symfony');
        $specialTag2->setCategory('framework');
        $this->em->persist($specialTag2);

        $article = new ArticleWithSpecialTags();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $article->addInternalSpecialTag($specialTag1);  // Use direct ManyToMany relationship
        $article->addInternalSpecialTag($specialTag2);  // Use direct ManyToMany relationship
        $this->em->persist($article);

        $this->em->flush();
        $articleId = $article->getId();
        $tag1Id = $specialTag1->getId();
        $tag2Id = $specialTag2->getId();
        $this->em->clear();

        // Include specialTags (should resolve to articleSpecialTags.specialTag)
        $request = $this->createJsonApiGetRequest('GET', "/api/articles-with-special-tags/{$articleId}?include=specialTags");
        $response = ($this->resourceController)($request, 'articles-with-special-tags', $articleId);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Verify article has specialTags relationship
        self::assertArrayHasKey('relationships', $document['data']);
        self::assertArrayHasKey('specialTags', $document['data']['relationships']);

        $specialTagsData = $document['data']['relationships']['specialTags']['data'];
        self::assertIsArray($specialTagsData);
        self::assertCount(2, $specialTagsData);

        // Verify included section contains SpecialTag resources
        self::assertArrayHasKey('included', $document);
        self::assertCount(2, $document['included']);

        $includedTypes = array_column($document['included'], 'type');
        self::assertContains('special-tags', $includedTypes);
        self::assertNotContains('article-with-special-tags-special-tags', $includedTypes); // ArticleWithSpecialTagsSpecialTag is NOT a resource

        $includedIds = array_column($document['included'], 'id');
        self::assertContains($tag1Id, $includedIds);
        self::assertContains($tag2Id, $includedIds);

        // Verify attributes
        foreach ($document['included'] as $included) {
            self::assertSame('special-tags', $included['type']);
            self::assertArrayHasKey('name', $included['attributes']);
            self::assertArrayHasKey('category', $included['attributes']);
        }
    }

    /**
     * Test 4: Filter by nested field through alias.
     *
     * Validates:
     * - filter[specialTags.category]=value works
     * - Multiple levels of path resolution
     */
    public function testFilterByNestedFieldThroughAlias(): void
    {
        // Create test data
        $author = new AuthorForSpecialTags();
        $author->setName('Test Author');
        $author->setEmail('author@example.com');
        $this->em->persist($author);

        $languageTag = new SpecialTag();
        $languageTag->setName('PHP');
        $languageTag->setCategory('language');
        $this->em->persist($languageTag);

        $frameworkTag = new SpecialTag();
        $frameworkTag->setName('Symfony');
        $frameworkTag->setCategory('framework');
        $this->em->persist($frameworkTag);

        $article1 = new ArticleWithSpecialTags();
        $article1->setTitle('Language Article');
        $article1->setContent('Content 1');
        $article1->setAuthor($author);
        $article1->addInternalSpecialTag($languageTag);  // Use direct ManyToMany relationship
        $this->em->persist($article1);

        $article2 = new ArticleWithSpecialTags();
        $article2->setTitle('Framework Article');
        $article2->setContent('Content 2');
        $article2->setAuthor($author);
        $article2->addInternalSpecialTag($frameworkTag);  // Use direct ManyToMany relationship
        $this->em->persist($article2);

        $this->em->flush();
        $this->em->clear();

        // Filter by category
        $request = $this->createJsonApiGetRequest('GET', '/api/articles-with-special-tags?filter[specialTags.category]=language');
        $response = ($this->collectionController)($request, 'articles-with-special-tags');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);

        // Should return only article1
        self::assertCount(1, $document['data']);
        self::assertSame('Language Article', $document['data'][0]['attributes']['title']);
    }

    /**
     * Helper method to create JSON:API GET request.
     */
    private function createJsonApiGetRequest(string $method, string $uri, array $query = []): Request
    {
        return Request::create(
            $uri,
            $method,
            $query,
            [],
            [],
            ['HTTP_ACCEPT' => MediaType::JSON_API]
        );
    }


}
