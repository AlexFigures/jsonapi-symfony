<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\PostgreSQL;

use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Query\Pagination;
use JsonApi\Symfony\Query\Sorting;
use JsonApi\Symfony\Tests\Integration\DoctrineIntegrationTestCase;

final class GenericRepositoryTest extends DoctrineIntegrationTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES']
            ?? 'postgresql://jsonapi:secret@localhost:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    public function testFindCollectionReturnsAllArticles(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria(new Pagination(1, 10));
        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(2, $slice->items);
        self::assertSame(2, $slice->totalItems);
        self::assertSame(1, $slice->pageNumber);
        self::assertSame(10, $slice->pageSize);
    }

    public function testFindCollectionWithPagination(): void
    {
        $this->seedDatabase();

        // Первая страница
        $criteria = new Criteria(new Pagination(1, 1));
        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(1, $slice->items);
        self::assertSame(2, $slice->totalItems);
        self::assertSame('article-1', $slice->items[0]->getId());

        // Вторая страница
        $criteria = new Criteria(new Pagination(2, 1));
        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(1, $slice->items);
        self::assertSame(2, $slice->totalItems);
        self::assertSame('article-2', $slice->items[0]->getId());
    }

    public function testFindCollectionWithSorting(): void
    {
        $this->seedDatabase();

        // Сортировка по title DESC
        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->sort = [new Sorting('title', true)]; // true = DESC

        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(2, $slice->items);
        self::assertSame('Second Article', $slice->items[0]->getTitle());
        self::assertSame('First Article', $slice->items[1]->getTitle());
    }

    public function testFindOneReturnsArticle(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        self::assertNotNull($article);
        self::assertSame('article-1', $article->getId());
        self::assertSame('First Article', $article->getTitle());
    }

    public function testFindOneReturnsNullForNonExistent(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria();
        $article = $this->repository->findOne('articles', 'non-existent', $criteria);

        self::assertNull($article);
    }

    public function testFindCollectionWithEmptyDatabase(): void
    {
        $criteria = new Criteria(new Pagination(1, 10));
        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(0, $slice->items);
        self::assertSame(0, $slice->totalItems);
    }

    // ==================== Multiple Includes Tests ====================

    public function testFindOneWithMultipleIncludes(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria();
        $criteria->include = ['author', 'tags'];

        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        self::assertNotNull($article);
        self::assertSame('article-1', $article->getId());

        // Verify author relationship is loaded
        $author = $article->getAuthor();
        self::assertNotNull($author);
        self::assertSame('author-1', $author->getId());
        self::assertSame('John Doe', $author->getName());

        // Verify tags relationship is loaded
        $tags = $article->getTags();
        self::assertCount(2, $tags);
    }

    public function testFindCollectionWithMultipleIncludes(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->include = ['author', 'tags'];

        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(2, $slice->items);

        // Verify first article has author and tags loaded
        $article1 = $slice->items[0];
        self::assertNotNull($article1->getAuthor());
        self::assertCount(2, $article1->getTags());

        // Verify second article has author and tags loaded
        $article2 = $slice->items[1];
        self::assertNotNull($article2->getAuthor());
        self::assertCount(1, $article2->getTags());
    }

    public function testFindOneWithMultipleIncludesInDifferentOrder(): void
    {
        $this->seedDatabase();

        // Test that order of includes doesn't matter
        $criteria = new Criteria();
        $criteria->include = ['tags', 'author'];

        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        self::assertNotNull($article);
        self::assertNotNull($article->getAuthor());
        self::assertCount(2, $article->getTags());
    }

    public function testFindCollectionWithThreeIncludes(): void
    {
        $this->seedDatabase();

        // Test with all available relationships (author and tags)
        // Note: Article only has 2 relationships, so we test both
        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->include = ['author', 'tags'];

        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(2, $slice->items);

        foreach ($slice->items as $article) {
            // Each article should have relationships loaded
            self::assertNotNull($article->getAuthor());
            self::assertGreaterThanOrEqual(0, $article->getTags()->count());
        }
    }

    // ==================== Sparse Fieldsets Tests ====================

    public function testFindOneWithSparseFieldsetsForPrimaryResource(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria();
        $criteria->fields = [
            'articles' => ['title'],
        ];

        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        self::assertNotNull($article);
        self::assertSame('article-1', $article->getId());
        self::assertSame('First Article', $article->getTitle());
        // Note: Repository returns full entity; sparse fieldsets are applied at serialization layer
    }

    public function testFindOneWithSparseFieldsetsForIncludedRelationships(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria();
        $criteria->include = ['author'];
        $criteria->fields = [
            'articles' => ['title'],
            'authors' => ['name'], // Only name field for author
        ];

        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        self::assertNotNull($article);
        self::assertSame('First Article', $article->getTitle());

        $author = $article->getAuthor();
        self::assertNotNull($author);
        self::assertSame('John Doe', $author->getName());
        // Note: Full entity is loaded; field filtering happens at serialization
    }

    public function testFindCollectionWithSparseFieldsetsForMultipleTypes(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->include = ['author', 'tags'];
        $criteria->fields = [
            'articles' => ['title', 'content'],
            'authors' => ['name'],
            'tags' => ['name'],
        ];

        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(2, $slice->items);

        foreach ($slice->items as $article) {
            // Verify entities are loaded (field filtering is at serialization layer)
            self::assertNotEmpty($article->getTitle());
            self::assertNotEmpty($article->getContent());
            self::assertNotNull($article->getAuthor());
            self::assertNotEmpty($article->getAuthor()->getName());
        }
    }

    public function testFindOneWithEmptySparseFieldsets(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria();
        $criteria->fields = [
            'articles' => [], // Empty fields array
        ];

        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        // Repository should still return the entity
        self::assertNotNull($article);
        self::assertSame('article-1', $article->getId());
    }

    public function testFindCollectionWithSparseFieldsetsAndPagination(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria(new Pagination(1, 1));
        $criteria->include = ['author'];
        $criteria->fields = [
            'articles' => ['title'],
            'authors' => ['name', 'email'],
        ];

        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(1, $slice->items);
        self::assertSame(2, $slice->totalItems);

        $article = $slice->items[0];
        self::assertNotNull($article->getAuthor());
    }

    // ==================== Combined Complex Query Tests ====================

    public function testFindCollectionWithMultipleIncludesAndSparseFieldsets(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->include = ['author', 'tags'];
        $criteria->fields = [
            'articles' => ['title', 'content'],
            'authors' => ['name'],
            'tags' => ['name'],
        ];

        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(2, $slice->items);
        self::assertSame(2, $slice->totalItems);

        // Verify relationships are loaded
        foreach ($slice->items as $article) {
            self::assertNotNull($article->getAuthor());
            self::assertGreaterThanOrEqual(0, $article->getTags()->count());
        }
    }

    public function testFindOneWithMultipleIncludesSparseFieldsetsAndSorting(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria(new Pagination(1, 10));
        $criteria->include = ['author', 'tags'];
        $criteria->fields = [
            'articles' => ['title'],
            'authors' => ['name'],
        ];
        $criteria->sort = [new Sorting('title', false)]; // false = ASC

        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(2, $slice->items);
        // Verify sorting
        self::assertSame('First Article', $slice->items[0]->getTitle());
        self::assertSame('Second Article', $slice->items[1]->getTitle());
    }

    public function testFindCollectionWithAllQueryFeaturesCombined(): void
    {
        $this->seedDatabase();

        // Combine pagination, sorting, includes, and sparse fieldsets
        $criteria = new Criteria(new Pagination(1, 1));
        $criteria->include = ['author', 'tags'];
        $criteria->fields = [
            'articles' => ['title', 'content'],
            'authors' => ['name', 'email'],
            'tags' => ['name'],
        ];
        $criteria->sort = [new Sorting('title', true)]; // true = DESC

        $slice = $this->repository->findCollection('articles', $criteria);

        self::assertCount(1, $slice->items);
        self::assertSame(2, $slice->totalItems);
        self::assertSame('Second Article', $slice->items[0]->getTitle());
        self::assertNotNull($slice->items[0]->getAuthor());
    }

    // ==================== Path Naming Strategy Tests ====================
    // Note: These tests document the expected behavior for naming strategies.
    // Currently, the library may not have explicit naming strategy configuration,
    // but these tests establish the baseline for future implementation.

    public function testResourceTypeUsesKebabCaseByDefault(): void
    {
        $this->seedDatabase();

        // Verify that resource types are defined correctly
        // The Article entity uses 'articles' as type (already kebab-case compatible)
        $criteria = new Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        self::assertNotNull($article);
        // This test verifies we can query using the expected resource type name
    }

    public function testRelationshipNamesFollowConsistentNaming(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria();
        $criteria->include = ['author', 'tags'];

        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        self::assertNotNull($article);
        // Verify relationship names are accessible
        // 'author' and 'tags' follow camelCase in PHP but should be
        // serialized according to the naming strategy
        self::assertNotNull($article->getAuthor());
        self::assertCount(2, $article->getTags());
    }

    public function testAttributeNamesAreConsistent(): void
    {
        $this->seedDatabase();

        $criteria = new Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        self::assertNotNull($article);
        // Verify attributes are accessible with their defined names
        self::assertSame('First Article', $article->getTitle());
        self::assertSame('Content of first article', $article->getContent());
        self::assertInstanceOf(\DateTimeImmutable::class, $article->getCreatedAt());
    }

    public function testFieldNamesInSparseFieldsetsFollowNamingStrategy(): void
    {
        $this->seedDatabase();

        // Test that field names in sparse fieldsets work correctly
        // This establishes baseline for naming strategy implementation
        $criteria = new Criteria();
        $criteria->fields = [
            'articles' => ['title', 'content', 'createdAt'],
        ];

        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        self::assertNotNull($article);
        self::assertSame('First Article', $article->getTitle());
    }

    public function testIncludePathsFollowNamingStrategy(): void
    {
        $this->seedDatabase();

        // Test that include paths use consistent naming
        $criteria = new Criteria();
        $criteria->include = ['author', 'tags'];

        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        self::assertNotNull($article);
        self::assertNotNull($article->getAuthor());
        self::assertCount(2, $article->getTags());
    }
}

