<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use JsonApi\Symfony\Filter\Ast\Comparison;
use JsonApi\Symfony\Filter\Ast\Conjunction;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Pagination;
use JsonApi\Symfony\Query\Sorting;
use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;

/**
 * Integration test for GenericDoctrineRepository with filtering and eager loading.
 */
final class GenericDoctrineRepositoryTest extends DoctrineIntegrationTestCase
{
    private GenericDoctrineRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = $this->getContainer()->get(GenericDoctrineRepository::class);
        $this->loadFixtures();
    }

    public function testFindCollectionWithoutFilters(): void
    {
        $criteria = new Criteria(new Pagination(1, 10));
        
        $slice = $this->repository->findCollection('articles', $criteria);
        
        $this->assertSame(1, $slice->pageNumber);
        $this->assertSame(10, $slice->pageSize);
        $this->assertGreaterThan(0, $slice->totalItems);
        $this->assertLessThanOrEqual(10, count($slice->items));
    }

    public function testFindCollectionWithEqualFilter(): void
    {
        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->filter = new Comparison('status', 'eq', ['published']);
        
        $slice = $this->repository->findCollection('articles', $criteria);
        
        foreach ($slice->items as $article) {
            $this->assertInstanceOf(Article::class, $article);
            $this->assertSame('published', $article->getStatus());
        }
    }

    public function testFindCollectionWithLikeFilter(): void
    {
        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->filter = new Comparison('title', 'like', ['Test']);
        
        $slice = $this->repository->findCollection('articles', $criteria);
        
        foreach ($slice->items as $article) {
            $this->assertInstanceOf(Article::class, $article);
            $this->assertStringContainsString('Test', $article->getTitle());
        }
    }

    public function testFindCollectionWithInFilter(): void
    {
        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->filter = new Comparison('status', 'in', ['published', 'draft']);
        
        $slice = $this->repository->findCollection('articles', $criteria);
        
        foreach ($slice->items as $article) {
            $this->assertInstanceOf(Article::class, $article);
            $this->assertContains($article->getStatus(), ['published', 'draft']);
        }
    }

    public function testFindCollectionWithRangeFilters(): void
    {
        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->filter = new Conjunction([
            new Comparison('viewCount', 'gte', [50]),
            new Comparison('viewCount', 'lte', [150]),
        ]);
        
        $slice = $this->repository->findCollection('articles', $criteria);
        
        foreach ($slice->items as $article) {
            $this->assertInstanceOf(Article::class, $article);
            $this->assertGreaterThanOrEqual(50, $article->getViewCount());
            $this->assertLessThanOrEqual(150, $article->getViewCount());
        }
    }

    public function testFindCollectionWithSorting(): void
    {
        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->sort = [new Sorting('viewCount', true)]; // DESC
        
        $slice = $this->repository->findCollection('articles', $criteria);
        
        $previousViewCount = PHP_INT_MAX;
        foreach ($slice->items as $article) {
            $this->assertInstanceOf(Article::class, $article);
            $this->assertLessThanOrEqual($previousViewCount, $article->getViewCount());
            $previousViewCount = $article->getViewCount();
        }
    }

    public function testFindCollectionWithPagination(): void
    {
        $criteria1 = new Criteria(new Pagination(1, 5));
        $slice1 = $this->repository->findCollection('articles', $criteria1);
        
        $this->assertCount(5, $slice1->items);
        $this->assertSame(1, $slice1->pageNumber);
        
        $criteria2 = new Criteria(new Pagination(2, 5));
        $slice2 = $this->repository->findCollection('articles', $criteria2);
        
        $this->assertSame(2, $slice2->pageNumber);
        
        // Ensure different items on different pages
        $ids1 = array_map(fn($a) => $a->getId(), $slice1->items);
        $ids2 = array_map(fn($a) => $a->getId(), $slice2->items);
        $this->assertEmpty(array_intersect($ids1, $ids2));
    }

    public function testFindCollectionWithEagerLoading(): void
    {
        $criteria = new Criteria(new Pagination(1, 5));
        $criteria->include = ['author'];
        
        // Clear entity manager to ensure fresh load
        $this->getEntityManager()->clear();
        
        $slice = $this->repository->findCollection('articles', $criteria);
        
        // Access author without triggering lazy loading
        foreach ($slice->items as $article) {
            $this->assertInstanceOf(Article::class, $article);
            $author = $article->getAuthor();
            
            if ($author !== null) {
                $this->assertInstanceOf(Author::class, $author);
                // Author should be loaded (not a proxy or already initialized)
                $this->assertTrue($this->getEntityManager()->getUnitOfWork()->isInIdentityMap($author));
            }
        }
    }

    public function testFindCollectionWithNestedEagerLoading(): void
    {
        $criteria = new Criteria(new Pagination(1, 5));
        $criteria->include = ['author', 'tags'];
        
        // Clear entity manager to ensure fresh load
        $this->getEntityManager()->clear();
        
        $slice = $this->repository->findCollection('articles', $criteria);
        
        foreach ($slice->items as $article) {
            $this->assertInstanceOf(Article::class, $article);
            
            // Author should be loaded
            $author = $article->getAuthor();
            if ($author !== null) {
                $this->assertInstanceOf(Author::class, $author);
            }
            
            // Tags should be loaded
            $tags = $article->getTags();
            $this->assertNotNull($tags);
        }
    }

    public function testFindOne(): void
    {
        // First, get an article ID
        $criteria = new Criteria(new Pagination(1, 1));
        $slice = $this->repository->findCollection('articles', $criteria);
        $this->assertNotEmpty($slice->items);
        
        $article = $slice->items[0];
        $id = (string) $article->getId();
        
        // Now find it by ID
        $found = $this->repository->findOne('articles', $id, new Criteria());
        
        $this->assertNotNull($found);
        $this->assertInstanceOf(Article::class, $found);
        $this->assertSame($id, (string) $found->getId());
    }

    public function testFindRelated(): void
    {
        // Get an article with author
        $criteria = new Criteria(new Pagination(1, 1));
        $criteria->filter = new Comparison('author', 'isnull', [false]);
        
        $slice = $this->repository->findCollection('articles', $criteria);
        $this->assertNotEmpty($slice->items);
        
        $article = $slice->items[0];
        $articleId = (string) $article->getId();
        
        // Find related author
        $related = $this->repository->findRelated('articles', 'author', [$articleId]);
        
        $this->assertNotEmpty($related);
        $relatedArray = iterator_to_array($related);
        $this->assertInstanceOf(Author::class, $relatedArray[0]);
    }

    public function testComplexQueryWithAllFeatures(): void
    {
        $criteria = new Criteria(new Pagination(1, 5));
        $criteria->filter = new Conjunction([
            new Comparison('status', 'eq', ['published']),
            new Comparison('viewCount', 'gte', [50]),
        ]);
        $criteria->sort = [new Sorting('viewCount', true)];
        $criteria->include = ['author'];
        
        $slice = $this->repository->findCollection('articles', $criteria);
        
        $this->assertLessThanOrEqual(5, count($slice->items));
        
        $previousViewCount = PHP_INT_MAX;
        foreach ($slice->items as $article) {
            $this->assertInstanceOf(Article::class, $article);
            $this->assertSame('published', $article->getStatus());
            $this->assertGreaterThanOrEqual(50, $article->getViewCount());
            $this->assertLessThanOrEqual($previousViewCount, $article->getViewCount());
            $previousViewCount = $article->getViewCount();
        }
    }

    private function loadFixtures(): void
    {
        $em = $this->getEntityManager();

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
        for ($i = 1; $i <= 20; $i++) {
            $article = new Article();
            $article->setTitle('Test Article ' . $i);
            $article->setContent('Content for article ' . $i);
            $article->setStatus($i % 2 === 0 ? 'published' : 'draft');
            $article->setViewCount($i * 10);
            $article->setAuthor($authors[$i % count($authors)]);
            $em->persist($article);
        }

        $em->flush();
    }
}

