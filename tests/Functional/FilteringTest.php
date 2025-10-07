<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;

/**
 * Test filtering functionality with all operators.
 */
final class FilteringTest extends JsonApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixtures();
    }

    public function testFilterWithEqualOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[title][eq]=Test Article 1');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        $this->assertCount(1, $data);
        $this->assertSame('Test Article 1', $data[0]['attributes']['title']);
    }

    public function testFilterWithNotEqualOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[title][ne]=Test Article 1');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        $this->assertGreaterThan(0, count($data));
        
        foreach ($data as $article) {
            $this->assertNotSame('Test Article 1', $article['attributes']['title']);
        }
    }

    public function testFilterWithLessThanOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[viewCount][lt]=100');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        
        foreach ($data as $article) {
            $this->assertLessThan(100, $article['attributes']['viewCount']);
        }
    }

    public function testFilterWithLessOrEqualOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[viewCount][lte]=100');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        
        foreach ($data as $article) {
            $this->assertLessThanOrEqual(100, $article['attributes']['viewCount']);
        }
    }

    public function testFilterWithGreaterThanOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[viewCount][gt]=50');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        
        foreach ($data as $article) {
            $this->assertGreaterThan(50, $article['attributes']['viewCount']);
        }
    }

    public function testFilterWithGreaterOrEqualOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[viewCount][gte]=50');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        
        foreach ($data as $article) {
            $this->assertGreaterThanOrEqual(50, $article['attributes']['viewCount']);
        }
    }

    public function testFilterWithLikeOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[title][like]=Test');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        $this->assertGreaterThan(0, count($data));
        
        foreach ($data as $article) {
            $this->assertStringContainsString('Test', $article['attributes']['title']);
        }
    }

    public function testFilterWithInOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[status][in][]=published&filter[status][in][]=draft');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        
        foreach ($data as $article) {
            $this->assertContains($article['attributes']['status'], ['published', 'draft']);
        }
    }

    public function testFilterWithIsNullOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[deletedAt][isnull]=true');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        
        foreach ($data as $article) {
            $this->assertNull($article['attributes']['deletedAt'] ?? null);
        }
    }

    public function testFilterWithBetweenOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[viewCount][between][]=10&filter[viewCount][between][]=100');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        
        foreach ($data as $article) {
            $viewCount = $article['attributes']['viewCount'];
            $this->assertGreaterThanOrEqual(10, $viewCount);
            $this->assertLessThanOrEqual(100, $viewCount);
        }
    }

    public function testFilterWithConjunction(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[and][0][title][like]=Test&filter[and][1][status][eq]=published');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        
        foreach ($data as $article) {
            $this->assertStringContainsString('Test', $article['attributes']['title']);
            $this->assertSame('published', $article['attributes']['status']);
        }
    }

    public function testFilterWithDisjunction(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[or][0][status][eq]=published&filter[or][1][status][eq]=draft');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        
        foreach ($data as $article) {
            $this->assertContains($article['attributes']['status'], ['published', 'draft']);
        }
    }

    public function testFilterWithPagination(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[status][eq]=published&page[number]=1&page[size]=5');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        $this->assertLessThanOrEqual(5, count($data));
        
        foreach ($data as $article) {
            $this->assertSame('published', $article['attributes']['status']);
        }
    }

    public function testFilterWithSorting(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[status][eq]=published&sort=-viewCount');

        $this->assertResponseIsSuccessful();
        $this->assertJsonApiResponse($response);
        
        $data = $response->toArray()['data'];
        
        $previousViewCount = PHP_INT_MAX;
        foreach ($data as $article) {
            $this->assertSame('published', $article['attributes']['status']);
            $viewCount = $article['attributes']['viewCount'];
            $this->assertLessThanOrEqual($previousViewCount, $viewCount);
            $previousViewCount = $viewCount;
        }
    }

    public function testInvalidFilterOperator(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[title][invalid]=test', [
            'http_errors' => false,
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonApiErrorResponse($response);
    }

    public function testInvalidFilterField(): void
    {
        $response = $this->client->request('GET', '/api/articles?filter[nonExistentField][eq]=test', [
            'http_errors' => false,
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonApiErrorResponse($response);
    }

    private function loadFixtures(): void
    {
        $em = $this->getEntityManager();

        // Create authors
        $author1 = new Author();
        $author1->setName('John Doe');
        $author1->setEmail('john@example.com');
        $em->persist($author1);

        $author2 = new Author();
        $author2->setName('Jane Smith');
        $author2->setEmail('jane@example.com');
        $em->persist($author2);

        // Create articles
        for ($i = 1; $i <= 20; $i++) {
            $article = new Article();
            $article->setTitle('Test Article ' . $i);
            $article->setContent('Content for article ' . $i);
            $article->setStatus($i % 2 === 0 ? 'published' : 'draft');
            $article->setViewCount($i * 10);
            $article->setAuthor($i % 2 === 0 ? $author1 : $author2);
            $em->persist($article);
        }

        $em->flush();
    }
}

