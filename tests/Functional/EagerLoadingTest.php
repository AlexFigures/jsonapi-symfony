<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use Doctrine\DBAL\Logging\DebugStack;
use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;
use JsonApi\Symfony\Tests\Fixtures\Model\Tag;

/**
 * Test that eager loading prevents N+1 query problems.
 */
final class EagerLoadingTest extends JsonApiTestCase
{
    private DebugStack $queryLogger;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable query logging
        $this->queryLogger = new DebugStack();
        $this->getEntityManager()->getConnection()->getConfiguration()->setSQLLogger($this->queryLogger);
        
        $this->loadFixtures();
    }

    protected function tearDown(): void
    {
        // Disable query logging
        $this->getEntityManager()->getConnection()->getConfiguration()->setSQLLogger(null);
        parent::tearDown();
    }

    public function testIncludeWithoutN1Queries(): void
    {
        // Reset query log
        $this->queryLogger->queries = [];

        // Request articles with author included
        $response = $this->client->request('GET', '/api/articles?include=author&page[size]=10');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('included', $data);
        
        // Count queries
        $queryCount = count($this->queryLogger->queries);
        
        // Should be approximately:
        // 1. SELECT articles with JOIN author
        // 2. COUNT query for pagination
        // Total: ~2-3 queries (not 1 + N where N is number of articles)
        $this->assertLessThan(5, $queryCount, 
            sprintf('Expected less than 5 queries, got %d. Queries: %s', 
                $queryCount, 
                json_encode(array_column($this->queryLogger->queries, 'sql'))
            )
        );
    }

    public function testNestedIncludeWithoutN1Queries(): void
    {
        // Reset query log
        $this->queryLogger->queries = [];

        // Request articles with nested includes
        $response = $this->client->request('GET', '/api/articles?include=author,tags&page[size]=5');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('included', $data);
        
        // Count queries
        $queryCount = count($this->queryLogger->queries);
        
        // Should be approximately:
        // 1. SELECT articles with JOIN author and JOIN tags
        // 2. COUNT query for pagination
        // Total: ~2-4 queries (not 1 + N + M where N is articles and M is tags)
        $this->assertLessThan(6, $queryCount, 
            sprintf('Expected less than 6 queries, got %d. Queries: %s', 
                $queryCount, 
                json_encode(array_column($this->queryLogger->queries, 'sql'))
            )
        );
    }

    public function testFilteringWithIncludeWithoutN1Queries(): void
    {
        // Reset query log
        $this->queryLogger->queries = [];

        // Request filtered articles with includes
        $response = $this->client->request('GET', '/api/articles?filter[status][eq]=published&include=author&page[size]=5');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray();
        $this->assertArrayHasKey('data', $data);
        
        // Count queries
        $queryCount = count($this->queryLogger->queries);
        
        // Should be approximately:
        // 1. SELECT articles with WHERE and JOIN author
        // 2. COUNT query for pagination
        // Total: ~2-4 queries
        $this->assertLessThan(6, $queryCount, 
            sprintf('Expected less than 6 queries, got %d. Queries: %s', 
                $queryCount, 
                json_encode(array_column($this->queryLogger->queries, 'sql'))
            )
        );
        
        // Verify filtering worked
        foreach ($data['data'] as $article) {
            $this->assertSame('published', $article['attributes']['status']);
        }
    }

    public function testManyToManyIncludeWithoutN1Queries(): void
    {
        // Reset query log
        $this->queryLogger->queries = [];

        // Request articles with tags (ManyToMany relationship)
        $response = $this->client->request('GET', '/api/articles?include=tags&page[size]=5');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray();
        $this->assertArrayHasKey('data', $data);
        
        // Count queries
        $queryCount = count($this->queryLogger->queries);
        
        // Should use JOIN to fetch tags in same query
        $this->assertLessThan(5, $queryCount, 
            sprintf('Expected less than 5 queries for ManyToMany, got %d. Queries: %s', 
                $queryCount, 
                json_encode(array_column($this->queryLogger->queries, 'sql'))
            )
        );
    }

    public function testComplexQueryWithMultipleIncludesAndFilters(): void
    {
        // Reset query log
        $this->queryLogger->queries = [];

        // Complex query with filtering, sorting, pagination, and includes
        $response = $this->client->request('GET', 
            '/api/articles?filter[viewCount][gte]=50&include=author,tags&sort=-viewCount&page[number]=1&page[size]=5'
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray();
        $this->assertArrayHasKey('data', $data);
        
        // Count queries
        $queryCount = count($this->queryLogger->queries);
        
        // Even with complex query, should not have N+1 problem
        $this->assertLessThan(8, $queryCount, 
            sprintf('Expected less than 8 queries for complex query, got %d. Queries: %s', 
                $queryCount, 
                json_encode(array_column($this->queryLogger->queries, 'sql'))
            )
        );
        
        // Verify results are correct
        $previousViewCount = PHP_INT_MAX;
        foreach ($data['data'] as $article) {
            $viewCount = $article['attributes']['viewCount'];
            $this->assertGreaterThanOrEqual(50, $viewCount);
            $this->assertLessThanOrEqual($previousViewCount, $viewCount);
            $previousViewCount = $viewCount;
        }
    }

    private function loadFixtures(): void
    {
        $em = $this->getEntityManager();

        // Create tags
        $tags = [];
        for ($i = 1; $i <= 5; $i++) {
            $tag = new Tag();
            $tag->setName('Tag ' . $i);
            $em->persist($tag);
            $tags[] = $tag;
        }

        // Create authors
        $authors = [];
        for ($i = 1; $i <= 3; $i++) {
            $author = new Author();
            $author->setName('Author ' . $i);
            $author->setEmail('author' . $i . '@example.com');
            $em->persist($author);
            $authors[] = $author;
        }

        // Create articles
        for ($i = 1; $i <= 30; $i++) {
            $article = new Article();
            $article->setTitle('Article ' . $i);
            $article->setContent('Content for article ' . $i);
            $article->setStatus($i % 2 === 0 ? 'published' : 'draft');
            $article->setViewCount($i * 10);
            $article->setAuthor($authors[$i % count($authors)]);
            
            // Add random tags
            $numTags = rand(1, 3);
            for ($j = 0; $j < $numTags; $j++) {
                $article->addTag($tags[rand(0, count($tags) - 1)]);
            }
            
            $em->persist($article);
        }

        $em->flush();
        $em->clear();
    }
}

