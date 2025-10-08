<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Resource\Relationship;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipLinkingPolicy;
use JsonApi\Symfony\Resource\Relationship\RelationshipResolver;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Tests\Integration\Resource\Relationship\Entities\Author;
use JsonApi\Symfony\Tests\Integration\Resource\Relationship\Entities\Book;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Test that relationships are properly saved when creating resources.
 */
class CreateWithRelationshipsTest extends TestCase
{
    private EntityManager $em;
    private RelationshipResolver $relationshipResolver;
    private ResourceRegistryInterface $registry;

    protected function setUp(): void
    {
        // Create in-memory SQLite database
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Entities'],
            isDevMode: true
        );

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        $this->em = new EntityManager($connection, $config);

        // Create schema
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);

        // Create mock registry with relationship metadata
        $this->registry = $this->createMock(ResourceRegistryInterface::class);
        $this->registry->method('getByType')->willReturnCallback(function (string $type) {
            return match ($type) {
                'authors' => new ResourceMetadata(
                    type: 'authors',
                    class: Author::class,
                    attributes: [],
                    relationships: [
                        'books' => new RelationshipMetadata(
                            name: 'books',
                            toMany: true,
                            targetType: 'books',
                            targetClass: Book::class,
                            linkingPolicy: RelationshipLinkingPolicy::REFERENCE,
                            writableOnCreate: true,
                            writableOnUpdate: true
                        ),
                    ]
                ),
                'books' => new ResourceMetadata(
                    type: 'books',
                    class: Book::class,
                    attributes: [],
                    relationships: [
                        'author' => new RelationshipMetadata(
                            name: 'author',
                            toMany: false,
                            targetType: 'authors',
                            targetClass: Author::class,
                            linkingPolicy: RelationshipLinkingPolicy::REFERENCE,
                            writableOnCreate: true,
                            writableOnUpdate: true
                        ),
                    ]
                ),
                default => throw new \RuntimeException("Unknown type: $type"),
            };
        });

        // Setup services
        $accessor = PropertyAccess::createPropertyAccessor();
        $errorBuilder = new ErrorBuilder(useDefaultTitleMap: true);
        $errorMapper = new ErrorMapper($errorBuilder);

        $this->relationshipResolver = new RelationshipResolver(
            $this->em,
            $this->registry,
            $accessor,
            $errorMapper,
            false
        );
    }

    protected function tearDown(): void
    {
        $this->em->close();
    }

    /**
     * Test creating a book with an author relationship (to-one).
     */
    public function testCreateBookWithAuthorRelationship(): void
    {
        // Create an author first
        $author = new Author('author-1', 'John Doe');
        $this->em->persist($author);
        $this->em->flush();
        $this->em->clear();

        // Create a book manually
        $book = new Book('book-1', 'Test Book');
        $this->em->persist($book);

        // Apply the relationship
        $relationships = [
            'author' => [
                'data' => [
                    'type' => 'authors',
                    'id' => 'author-1',
                ],
            ],
        ];

        $metadata = $this->registry->getByType('books');
        $this->relationshipResolver->applyRelationships(
            $book,
            $relationships,
            $metadata,
            isCreate: true
        );

        // Flush to save the relationship
        $this->em->flush();
        $this->em->clear();

        // Verify the relationship was saved
        /** @var Book $savedBook */
        $savedBook = $this->em->find(Book::class, 'book-1');
        self::assertNotNull($savedBook);
        self::assertNotNull($savedBook->getAuthor());
        self::assertSame('author-1', $savedBook->getAuthor()->getId());
        self::assertSame('John Doe', $savedBook->getAuthor()->getName());
    }

    /**
     * Test creating an author with books relationship (to-many).
     */
    public function testCreateAuthorWithBooksRelationship(): void
    {
        // Create books first
        $book1 = new Book('book-1', 'Book One');
        $book2 = new Book('book-2', 'Book Two');
        $this->em->persist($book1);
        $this->em->persist($book2);
        $this->em->flush();
        $this->em->clear();

        // Create an author manually
        $author = new Author('author-1', 'Jane Doe');
        $this->em->persist($author);

        // Apply the relationship
        $relationships = [
            'books' => [
                'data' => [
                    ['type' => 'books', 'id' => 'book-1'],
                    ['type' => 'books', 'id' => 'book-2'],
                ],
            ],
        ];

        $metadata = $this->registry->getByType('authors');
        $this->relationshipResolver->applyRelationships(
            $author,
            $relationships,
            $metadata,
            isCreate: true
        );

        // Flush to save the relationship
        $this->em->flush();
        $this->em->clear();

        // Verify the relationship was saved
        /** @var Author $savedAuthor */
        $savedAuthor = $this->em->find(Author::class, 'author-1');
        self::assertNotNull($savedAuthor);
        self::assertCount(2, $savedAuthor->getBooks());

        $bookIds = array_map(fn($book) => $book->getId(), $savedAuthor->getBooks()->toArray());
        self::assertContains('book-1', $bookIds);
        self::assertContains('book-2', $bookIds);
    }

    /**
     * Test creating a book with null author relationship.
     */
    public function testCreateBookWithNullAuthorRelationship(): void
    {
        // Create a book manually
        $book = new Book('book-1', 'Orphan Book');
        $this->em->persist($book);

        // Apply null relationship
        $relationships = [
            'author' => [
                'data' => null,
            ],
        ];

        $metadata = $this->registry->getByType('books');
        $this->relationshipResolver->applyRelationships(
            $book,
            $relationships,
            $metadata,
            isCreate: true
        );

        // Flush to save
        $this->em->flush();
        $this->em->clear();

        // Verify the relationship is null
        /** @var Book $savedBook */
        $savedBook = $this->em->find(Book::class, 'book-1');
        self::assertNotNull($savedBook);
        self::assertNull($savedBook->getAuthor());
    }

    /**
     * Test creating an author with empty books relationship.
     */
    public function testCreateAuthorWithEmptyBooksRelationship(): void
    {
        // Create an author manually
        $author = new Author('author-1', 'Lonely Author');
        $this->em->persist($author);

        // Apply empty relationship
        $relationships = [
            'books' => [
                'data' => [],
            ],
        ];

        $metadata = $this->registry->getByType('authors');
        $this->relationshipResolver->applyRelationships(
            $author,
            $relationships,
            $metadata,
            isCreate: true
        );

        // Flush to save
        $this->em->flush();
        $this->em->clear();

        // Verify the relationship is empty
        /** @var Author $savedAuthor */
        $savedAuthor = $this->em->find(Author::class, 'author-1');
        self::assertNotNull($savedAuthor);
        self::assertCount(0, $savedAuthor->getBooks());
    }
}

