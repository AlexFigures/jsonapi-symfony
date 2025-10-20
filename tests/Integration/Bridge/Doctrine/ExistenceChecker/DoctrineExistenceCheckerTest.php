<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Bridge\Doctrine\ExistenceChecker;

use AlexFigures\Symfony\Bridge\Doctrine\ExistenceChecker\DoctrineExistenceChecker;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;

/**
 * Integration tests for DoctrineExistenceChecker with real PostgreSQL database.
 */
final class DoctrineExistenceCheckerTest extends DoctrineIntegrationTestCase
{
    private DoctrineExistenceChecker $checker;

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

        $this->checker = new DoctrineExistenceChecker($this->managerRegistry, $this->registry);
    }

    /**
     * Test 1: exists() returns true when resource exists.
     */
    public function testExistsReturnsTrueWhenResourceExists(): void
    {
        $author = new Author();
        $author->setName('John Doe');
        $author->setEmail('john@example.com');
        $this->em->persist($author);
        $this->em->flush();

        $authorId = $author->getId();
        $this->em->clear();

        $exists = $this->checker->exists('authors', $authorId);

        self::assertTrue($exists);
    }

    /**
     * Test 2: exists() returns false when resource does not exist.
     */
    public function testExistsReturnsFalseWhenResourceDoesNotExist(): void
    {
        $exists = $this->checker->exists('authors', 'non-existent-id');

        self::assertFalse($exists);
    }

    /**
     * Test 3: exists() returns false when type is not registered.
     */
    public function testExistsReturnsFalseWhenTypeNotRegistered(): void
    {
        $exists = $this->checker->exists('unknown-type', 'some-id');

        self::assertFalse($exists);
    }

    /**
     * Test 4: exists() works with integer IDs.
     */
    public function testExistsWorksWithIntegerIds(): void
    {
        $author = new Author();
        $author->setName('Jane Smith');
        $author->setEmail('jane@example.com');
        $this->em->persist($author);

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Content');
        $article->setAuthor($author);
        $this->em->persist($article);

        $this->em->flush();

        $articleId = (string) $article->getId();
        $this->em->clear();

        $exists = $this->checker->exists('articles', $articleId);

        self::assertTrue($exists);
    }

    /**
     * Test 5: exists() works with UUID IDs.
     */
    public function testExistsWorksWithUuidIds(): void
    {
        $author = new Author();
        $author->setName('Alice Johnson');
        $author->setEmail('alice@example.com');
        $this->em->persist($author);
        $this->em->flush();

        $authorId = $author->getId();
        $this->em->clear();

        $exists = $this->checker->exists('authors', $authorId);

        self::assertTrue($exists);
    }

    /**
     * Test 6: exists() does not load entity into memory.
     */
    public function testExistsDoesNotLoadEntity(): void
    {
        $author = new Author();
        $author->setName('Bob Brown');
        $author->setEmail('bob@example.com');
        $this->em->persist($author);
        $this->em->flush();

        $authorId = $author->getId();
        $this->em->clear();

        // Verify entity is not in UnitOfWork before check
        self::assertFalse($this->em->getUnitOfWork()->isInIdentityMap($author));

        $exists = $this->checker->exists('authors', $authorId);

        self::assertTrue($exists);

        // Verify entity is still not in UnitOfWork after check
        // (it would be if we used find() instead of COUNT query)
        $identityMap = $this->em->getUnitOfWork()->getIdentityMap();
        $authorClass = Author::class;
        $isLoaded = isset($identityMap[$authorClass][$authorId]);

        self::assertFalse($isLoaded, 'Entity should not be loaded into memory by exists() check');
    }
}
