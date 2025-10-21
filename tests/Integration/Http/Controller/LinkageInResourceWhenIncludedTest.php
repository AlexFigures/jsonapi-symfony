<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Http\Controller;

use AlexFigures\Symfony\Http\Controller\CollectionController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Util\JsonApiResponseAsserts;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class LinkageInResourceWhenIncludedTest extends DoctrineIntegrationTestCase
{
    use JsonApiResponseAsserts;

    private CollectionController $controller;
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

        $routes = new RouteCollection();
        $routes->add('jsonapi.collection', new Route('/api/{type}'));
        $routes->add('jsonapi.resource', new Route('/api/{type}/{id}'));

        foreach (['articles', 'authors'] as $type) {
            $routes->add("jsonapi.{$type}.index", new Route("/api/{$type}"));
            $routes->add("jsonapi.{$type}.show", new Route("/api/{$type}/{id}"));
            $routes->add("jsonapi.{$type}.related.author", new Route("/api/{$type}/{id}/author"));
            $routes->add("jsonapi.{$type}.related.articles", new Route("/api/{$type}/{id}/articles"));
            $routes->add("jsonapi.{$type}.related.tags", new Route("/api/{$type}/{id}/tags"));
            $routes->add("jsonapi.{$type}.relationships.author.show", new Route("/api/{$type}/{id}/relationships/author"));
            $routes->add("jsonapi.{$type}.relationships.articles.show", new Route("/api/{$type}/{id}/relationships/articles"));
            $routes->add("jsonapi.{$type}.relationships.tags.show", new Route("/api/{$type}/{id}/relationships/tags"));
        }

        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = new UrlGenerator($routes, $context);
        $this->linkGenerator = new LinkGenerator($urlGenerator);

        $documentBuilder = new DocumentBuilder(
            $this->registry,
            $this->accessor,
            $this->linkGenerator,
            'when_included'
        );

        $errorMapper = new ErrorMapper(new ErrorBuilder(true));
        $paginationConfig = new PaginationConfig(defaultSize: 10, maxSize: 100);
        $sortingWhitelist = new SortingWhitelist($this->registry);
        $filteringWhitelist = new FilteringWhitelist($this->registry, $errorMapper);

        $queryParser = new QueryParser(
            $this->registry,
            $paginationConfig,
            $sortingWhitelist,
            $filteringWhitelist,
            $errorMapper,
            new \AlexFigures\Symfony\Filter\Parser\FilterParser()
        );

        $this->controller = new CollectionController(
            $this->registry,
            $this->repository,
            $queryParser,
            $documentBuilder
        );
    }

    public function testNestedIncludeAddsRelationshipDataForIncludedResources(): void
    {
        $author = new Author();
        $author->setName('Jane Doe');
        $author->setEmail('jane@example.com');
        $this->em->persist($author);

        $primaryArticle = new Article();
        $primaryArticle->setTitle('Primary Article');
        $primaryArticle->setContent('Primary Content');
        $primaryArticle->setAuthor($author);
        $this->em->persist($primaryArticle);

        $secondaryArticle = new Article();
        $secondaryArticle->setTitle('Secondary Article');
        $secondaryArticle->setContent('Secondary Content');
        $secondaryArticle->setAuthor($author);
        $this->em->persist($secondaryArticle);

        $primaryId = $primaryArticle->getId();
        $secondaryId = $secondaryArticle->getId();

        $this->em->flush();
        $this->em->clear();

        $request = $this->createJsonApiGetRequest('GET', '/api/articles?include=author.articles');
        $response = ($this->controller)($request, 'articles');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $document = $this->decode($response);
        self::assertArrayHasKey('included', $document);

        $authors = array_values(array_filter(
            $document['included'],
            static fn (array $item): bool => $item['type'] === 'authors'
        ));

        self::assertNotEmpty($authors);

        $includedAuthor = $authors[0];
        self::assertArrayHasKey('relationships', $includedAuthor);
        self::assertArrayHasKey('articles', $includedAuthor['relationships']);
        self::assertArrayHasKey('data', $includedAuthor['relationships']['articles']);

        $linkage = $includedAuthor['relationships']['articles']['data'];
        self::assertIsArray($linkage);
        self::assertNotEmpty($linkage);

        $ids = array_map(static fn (array $identifier): string => $identifier['id'], $linkage);
        self::assertContains($primaryId, $ids);
        self::assertContains($secondaryId, $ids);
    }

    private function createJsonApiGetRequest(string $method, string $uri, array $query = []): Request
    {
        $request = Request::create($uri, $method, $query);
        $request->headers->set('Accept', MediaType::JSON_API);

        return $request;
    }
}
