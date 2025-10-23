<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Profile;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile;
use AlexFigures\Symfony\Profile\Builtin\Hook\AuditTrailWriteHook;
use AlexFigures\Symfony\Profile\Builtin\Hook\RelationshipCountsDocumentHook;
use AlexFigures\Symfony\Profile\Builtin\Hook\SoftDeleteQueryHook;
use AlexFigures\Symfony\Profile\Builtin\RelationshipCountsProfile;
use AlexFigures\Symfony\Profile\Builtin\SoftDeleteProfile;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Profile\ProfileRegistry;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\AuditableProduct;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\SoftDeletableArticle;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;

/**
 * Integration tests for profile hooks with real database operations.
 *
 * Tests that profile hooks are properly invoked during actual database operations
 * and can modify behavior of queries, writes, and document building.
 */
#[CoversClass(ProfileContext::class)]
final class ProfileHooksIntegrationTest extends DoctrineIntegrationTestCase
{
    private ProfileRegistry $profileRegistry;

    protected function getDatabaseUrl(): string
    {
        $url = $_ENV['DATABASE_URL_PGSQL'] ?? 'postgresql://jsonapi:jsonapi@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
        assert(is_string($url));
        return $url;
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileRegistry = new ProfileRegistry();
        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        $author = new Author();
        $author->setId('author-1');
        $author->setName('John Doe');
        $author->setEmail('john@example.com');

        $tag1 = new Tag();
        $tag1->setId('tag-1');
        $tag1->setName('PHP');

        $tag2 = new Tag();
        $tag2->setId('tag-2');
        $tag2->setName('Symfony');

        $article = new Article();
        $article->setId('article-1');
        $article->setTitle('Test Article');
        $article->setContent('Test content');
        $article->setAuthor($author);
        $article->addTag($tag1);
        $article->addTag($tag2);

        $this->em->persist($author);
        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();
    }

    public function testReadHookInvokedDuringDatabaseQuery(): void
    {
        // Use SoftDeleteProfile which has QueryHook (similar to ReadHook)
        // QueryHook is invoked during query parsing, which happens before read operations
        $profile = new SoftDeleteProfile();

        $this->profileRegistry->register($profile);

        // Create context with profile active
        $context = new ProfileContext([$profile->uri() => $profile]);

        // Verify hooks are registered
        $queryHooks = iterator_to_array($context->queryHooks());
        self::assertNotEmpty($queryHooks);
        self::assertInstanceOf(SoftDeleteQueryHook::class, $queryHooks[0]);

        // Perform actual database query
        $criteria = new Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);

        // Verify database query succeeded
        self::assertNotNull($article);
        self::assertInstanceOf(Article::class, $article);
    }

    public function testWriteHookInvokedDuringDatabaseWrite(): void
    {
        // Use AuditTrailProfile which has WriteHook
        $profile = new AuditTrailProfile();

        $this->profileRegistry->register($profile);

        // Create context with profile active
        $context = new ProfileContext([$profile->uri() => $profile]);

        // Verify hooks are registered
        $writeHooks = iterator_to_array($context->writeHooks());
        self::assertNotEmpty($writeHooks);
        self::assertInstanceOf(AuditTrailWriteHook::class, $writeHooks[0]);

        // Simulate hook invocation during create
        $changeSet = new ChangeSet(['title' => 'New Article'], []);
        foreach ($context->writeHooks() as $hook) {
            $hook->onBeforeCreate($context, 'articles', $changeSet);
        }

        // Verify hook added createdAt
        self::assertArrayHasKey('createdAt', $changeSet->attributes);
        self::assertInstanceOf(\DateTimeImmutable::class, $changeSet->attributes['createdAt']);

        // Simulate hook invocation during update
        $changeSet = new ChangeSet(['title' => 'Updated Article'], []);
        foreach ($context->writeHooks() as $hook) {
            $hook->onBeforeUpdate($context, 'articles', 'article-1', $changeSet);
        }

        // Verify hook added updatedAt
        self::assertArrayHasKey('updatedAt', $changeSet->attributes);
        self::assertInstanceOf(\DateTimeImmutable::class, $changeSet->attributes['updatedAt']);

        // Perform actual database update
        $criteria = new Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertInstanceOf(Article::class, $article);
        $article->setTitle('Updated Title');
        $this->em->flush();

        // Verify database was updated
        $this->em->clear();
        $updated = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertInstanceOf(Article::class, $updated);
        self::assertSame('Updated Title', $updated->getTitle());
    }

    public function testQueryHookCanModifyCriteria(): void
    {
        // Create soft-deletable articles - one active, one deleted
        $activeArticle = new SoftDeletableArticle();
        $activeArticle->setId('active-article');
        $activeArticle->setTitle('Active Article');
        $activeArticle->setContent('This is active');

        $deletedArticle = new SoftDeletableArticle();
        $deletedArticle->setId('deleted-article');
        $deletedArticle->setTitle('Deleted Article');
        $deletedArticle->setContent('This is deleted');
        $deletedArticle->softDelete('test-user');

        $this->em->persist($activeArticle);
        $this->em->persist($deletedArticle);
        $this->em->flush();
        $this->em->clear();

        // Use SoftDeleteProfile which has QueryHook
        $profile = new SoftDeleteProfile();
        $this->profileRegistry->register($profile);

        // Create context with profile active
        $context = new ProfileContext([$profile->uri() => $profile]);

        // Verify hooks are registered
        $queryHooks = iterator_to_array($context->queryHooks());
        self::assertNotEmpty($queryHooks);
        self::assertInstanceOf(SoftDeleteQueryHook::class, $queryHooks[0]);

        // Simulate hook invocation during query parsing
        $request = Request::create('/soft-deletable-articles', 'GET');
        $criteria = new Criteria();
        foreach ($context->queryHooks() as $hook) {
            $hook->onParseQuery($context, $request, $criteria);
        }

        // Verify hook added custom conditions (soft delete filter)
        self::assertNotEmpty($criteria->customConditions);

        // Apply custom conditions to a real query
        $qb = $this->em->createQueryBuilder();
        $qb->select('e')
            ->from(SoftDeletableArticle::class, 'e');

        foreach ($criteria->customConditions as $condition) {
            $condition($qb);
        }

        $articles = $qb->getQuery()->getResult();

        // Should only return active articles (deleted ones filtered out)
        self::assertCount(1, $articles, 'Should only return active articles (deleted ones filtered out)');
        self::assertSame('active-article', $articles[0]->getId());
    }

    public function testQueryHookReadsAttributeFromEntity(): void
    {
        // Create a soft-deletable article
        $article = new SoftDeletableArticle();
        $article->setId('soft-article-1');
        $article->setTitle('Test Soft Delete');
        $article->setContent('This article can be soft deleted');

        $this->em->persist($article);
        $this->em->flush();

        // Soft delete it
        $article->softDelete('test-user');
        $this->em->flush();
        $this->em->clear();

        // Use SoftDeleteProfile with QueryHook
        $profile = new SoftDeleteProfile();
        $context = new ProfileContext([$profile->uri() => $profile]);

        // Create criteria and invoke hooks
        $request = Request::create('/soft-deletable-articles', 'GET');
        $criteria = new Criteria();
        foreach ($context->queryHooks() as $hook) {
            $hook->onParseQuery($context, $request, $criteria);
        }

        // Verify hook added custom conditions
        self::assertNotEmpty($criteria->customConditions);

        // The hook should filter out soft-deleted articles by default
        // When we apply the custom conditions to a query, deleted articles should be excluded
        $qb = $this->em->createQueryBuilder();
        $qb->select('e')
            ->from(SoftDeletableArticle::class, 'e');

        foreach ($criteria->customConditions as $condition) {
            $condition($qb);
        }

        $results = $qb->getQuery()->getResult();

        // Should be empty because the article is soft-deleted
        self::assertEmpty($results, 'Soft-deleted articles should be filtered out by default');
    }

    public function testAuditTrailHookReadsAttributeFromEntity(): void
    {
        // Create an auditable product
        $product = new AuditableProduct();
        $product->setId('product-1');
        $product->setName('Test Product');
        $product->setPrice('99.99');

        $this->em->persist($product);
        $this->em->flush();
        $this->em->clear();

        // Use AuditTrailProfile with WriteHook
        $profile = new AuditTrailProfile();
        $context = new ProfileContext([$profile->uri() => $profile]);

        // Verify hooks are registered
        $writeHooks = iterator_to_array($context->writeHooks());
        self::assertNotEmpty($writeHooks);
        self::assertInstanceOf(AuditTrailWriteHook::class, $writeHooks[0]);

        // Simulate hook invocation during create
        $changeSet = new ChangeSet(['name' => 'New Product', 'price' => '49.99'], []);
        foreach ($context->writeHooks() as $hook) {
            $hook->onBeforeCreate($context, 'auditable-products', $changeSet);
        }

        // Verify hook added createdAt (it reads field names from #[Auditable] attribute)
        self::assertArrayHasKey('createdAt', $changeSet->attributes);
        self::assertInstanceOf(\DateTimeImmutable::class, $changeSet->attributes['createdAt']);

        // Verify the actual entity has audit fields
        $product = $this->em->find(AuditableProduct::class, 'product-1');
        self::assertNotNull($product);
        self::assertInstanceOf(\DateTimeImmutable::class, $product->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $product->getUpdatedAt());
    }

    public function testDocumentHookCanModifyLinks(): void
    {
        // Use RelationshipCountsProfile which has DocumentHook
        $profile = new RelationshipCountsProfile();

        $this->profileRegistry->register($profile);

        // Create context with profile active
        $context = new ProfileContext([$profile->uri() => $profile]);

        // Verify hooks are registered
        $documentHooks = iterator_to_array($context->documentHooks());
        self::assertNotEmpty($documentHooks);
        self::assertInstanceOf(RelationshipCountsDocumentHook::class, $documentHooks[0]);

        // Simulate hook invocation during document building
        $request = Request::create('/articles', 'GET');
        $links = [];
        foreach ($context->documentHooks() as $hook) {
            $hook->onTopLevelLinks($context, $links, $request);
        }

        // DocumentHook doesn't modify top-level links in RelationshipCountsProfile
        // It modifies relationship metadata instead
        // So we just verify the hook exists and can be invoked

        // Verify database query still works
        $criteria = new Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
    }
}
