<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\PostgreSQL;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\ValidationException;
use JsonApi\Symfony\Resource\Metadata\RelationshipLinkingPolicy;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipSemantics;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Relationship\RelationshipResolver;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Tests\Integration\Resource\Relationship\Entities\Author;
use JsonApi\Symfony\Tests\Integration\Resource\Relationship\Entities\Book;
use JsonApi\Symfony\Tests\Integration\Resource\Relationship\Entities\Publisher;
use JsonApi\Symfony\Tests\Integration\Resource\Relationship\Entities\Tag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * PostgreSQL integration tests for RelationshipResolver.
 * 
 * @group integration
 */
class RelationshipResolverTest extends TestCase
{
    private EntityManager $em;
    private RelationshipResolver $resolver;
    private ResourceRegistryInterface $registry;
    private ErrorMapper $errorMapper;

    protected function setUp(): void
    {
        // Create PostgreSQL connection
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../Resource/Relationship/Entities'],
            isDevMode: true
        );

        $databaseUrl = $_ENV['DATABASE_URL_POSTGRES']
            ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';

        $connection = DriverManager::getConnection([
            'url' => $databaseUrl,
        ], $config);

        $this->em = new EntityManager($connection, $config);

        // Create schema
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        
        // Drop and recreate schema for clean state
        $metadata = [
            $this->em->getClassMetadata(Author::class),
            $this->em->getClassMetadata(Book::class),
            $this->em->getClassMetadata(Tag::class),
            $this->em->getClassMetadata(Publisher::class),
        ];
        
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Create mock registry
        $this->registry = $this->createMock(ResourceRegistryInterface::class);
        $errorBuilder = new ErrorBuilder(useDefaultTitleMap: true);
        $this->errorMapper = new ErrorMapper($errorBuilder);

        // Setup registry to return metadata for our test entities
        $this->registry->method('getByType')->willReturnCallback(function (string $type) {
            return match ($type) {
                'authors' => new ResourceMetadata('authors', Author::class, [], []),
                'books' => new ResourceMetadata('books', Book::class, [], []),
                'tags' => new ResourceMetadata('tags', Tag::class, [], []),
                'publishers' => new ResourceMetadata('publishers', Publisher::class, [], []),
                default => throw new \RuntimeException("Unknown type: $type"),
            };
        });

        $this->resolver = new RelationshipResolver(
            $this->em,
            $this->registry,
            PropertyAccess::createPropertyAccessor(),
            $this->errorMapper,
            false
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            // Clean up schema
            $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
            $metadata = [
                $this->em->getClassMetadata(Author::class),
                $this->em->getClassMetadata(Book::class),
                $this->em->getClassMetadata(Tag::class),
                $this->em->getClassMetadata(Publisher::class),
            ];
            $schemaTool->dropSchema($metadata);
            
            $this->em->close();
        }
    }

    /**
     * Test MERGE semantics: adding new items to existing collection without removing old ones
     */
    public function testMergeSemanticsAddsNewItemsWithoutRemovingExisting(): void
    {
        // Setup: Create author with 2 books
        $author = new Author('author-1', 'John Doe');
        $book1 = new Book('book-1', 'Book One');
        $book2 = new Book('book-2', 'Book Two');
        $book3 = new Book('book-3', 'Book Three');

        $author->addBook($book1);
        $author->addBook($book2);

        $this->em->persist($author);
        $this->em->persist($book1);
        $this->em->persist($book2);
        $this->em->persist($book3);
        $this->em->flush();
        $this->em->clear();

        // Reload author
        $author = $this->em->find(Author::class, 'author-1');
        $this->assertNotNull($author);
        $this->assertCount(2, $author->getBooks());

        // Apply relationship: add book-3 using MERGE semantics
        $metadata = new ResourceMetadata(
            type: 'authors',
            class: Author::class,
            attributes: [],
            relationships: [
                'books' => new RelationshipMetadata(
                    name: 'books',
                    toMany: true,
                    targetType: 'books',
                    targetClass: Book::class,
                    semantics: RelationshipSemantics::MERGE,
                    linkingPolicy: RelationshipLinkingPolicy::REFERENCE
                ),
            ]
        );

        $relationships = [
            'books' => [
                'data' => [
                    ['type' => 'books', 'id' => 'book-1'],
                    ['type' => 'books', 'id' => 'book-2'],
                    ['type' => 'books', 'id' => 'book-3'], // New book
                ],
            ],
        ];

        $this->resolver->applyRelationships($author, $relationships, $metadata, false);
        $this->em->flush();
        $this->em->clear();

        // Verify: all 3 books should be present
        $author = $this->em->find(Author::class, 'author-1');
        $this->assertNotNull($author);
        $this->assertCount(3, $author->getBooks());

        $bookIds = array_map(fn(Book $b) => $b->getId(), $author->getBooks()->toArray());
        $this->assertContains('book-1', $bookIds);
        $this->assertContains('book-2', $bookIds);
        $this->assertContains('book-3', $bookIds);
    }

    /**
     * Test REPLACE semantics: completely replacing collection contents
     */
    public function testReplaceSemanticsReplacesEntireCollection(): void
    {
        // Setup: Create book with 2 tags
        $book = new Book('book-7', 'Book Seven');
        $tag1 = new Tag('tag-1', 'Fiction');
        $tag2 = new Tag('tag-2', 'Adventure');
        $tag3 = new Tag('tag-3', 'Mystery');

        $book->addTag($tag1);
        $book->addTag($tag2);

        $this->em->persist($book);
        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($tag3);
        $this->em->flush();
        $this->em->clear();

        // Reload book
        $book = $this->em->find(Book::class, 'book-7');
        $this->assertNotNull($book);
        $this->assertCount(2, $book->getTags());

        // Apply relationship: replace with tag-3 only using REPLACE semantics
        $metadata = new ResourceMetadata(
            type: 'books',
            class: Book::class,
            attributes: [],
            relationships: [
                'tags' => new RelationshipMetadata(
                    name: 'tags',
                    toMany: true,
                    targetType: 'tags',
                    targetClass: Tag::class,
                    semantics: RelationshipSemantics::REPLACE,
                    linkingPolicy: RelationshipLinkingPolicy::REFERENCE
                ),
            ]
        );

        $relationships = [
            'tags' => [
                'data' => [
                    ['type' => 'tags', 'id' => 'tag-3'],
                ],
            ],
        ];

        $this->resolver->applyRelationships($book, $relationships, $metadata, false);
        $this->em->flush();
        $this->em->clear();

        // Verify: only tag-3 should remain
        $book = $this->em->find(Book::class, 'book-7');
        $this->assertNotNull($book);
        $this->assertCount(1, $book->getTags());
        $this->assertEquals('tag-3', $book->getTags()->first()->getId());
    }

    /**
     * Test that Doctrine PersistentCollection is never replaced with an array
     */
    public function testPersistentCollectionIsNeverReplaced(): void
    {
        // Setup: Create author with books
        $author = new Author('author-3', 'Test Author');
        $book1 = new Book('book-8', 'Book Eight');
        $author->addBook($book1);

        $this->em->persist($author);
        $this->em->persist($book1);
        $this->em->flush();

        // Get the collection instance before modification
        $originalCollection = $author->getBooks();
        $originalCollectionClass = get_class($originalCollection);

        // Apply relationship modification
        $metadata = new ResourceMetadata(
            type: 'authors',
            class: Author::class,
            attributes: [],
            relationships: [
                'books' => new RelationshipMetadata(
                    name: 'books',
                    toMany: true,
                    targetType: 'books',
                    targetClass: Book::class,
                    semantics: RelationshipSemantics::MERGE,
                    linkingPolicy: RelationshipLinkingPolicy::REFERENCE
                ),
            ]
        );

        $book2 = new Book('book-9', 'Book Nine');
        $this->em->persist($book2);
        $this->em->flush();

        $relationships = [
            'books' => [
                'data' => [
                    ['type' => 'books', 'id' => 'book-8'],
                    ['type' => 'books', 'id' => 'book-9'],
                ],
            ],
        ];

        $this->resolver->applyRelationships($author, $relationships, $metadata, false);

        // Verify: collection instance should be the same
        $this->assertSame($originalCollection, $author->getBooks(), 'Collection instance should not be replaced');
        $this->assertInstanceOf(Collection::class, $author->getBooks());
        $this->assertEquals($originalCollectionClass, get_class($author->getBooks()));
    }

    /**
     * Test bidirectional to-many relationship: verify both sides are synchronized after PATCH
     */
    public function testBidirectionalToManySynchronization(): void
    {
        // Setup: Create book and tags
        $book = new Book('book-10', 'Book Ten');
        $tag1 = new Tag('tag-4', 'Science');
        $tag2 = new Tag('tag-5', 'Technology');

        $this->em->persist($book);
        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->flush();
        $this->em->clear();

        // Reload entities
        $book = $this->em->find(Book::class, 'book-10');
        $this->assertNotNull($book);

        // Apply relationship: add tags to book
        $metadata = new ResourceMetadata(
            type: 'books',
            class: Book::class,
            attributes: [],
            relationships: [
                'tags' => new RelationshipMetadata(
                    name: 'tags',
                    toMany: true,
                    targetType: 'tags',
                    targetClass: Tag::class,
                    semantics: RelationshipSemantics::MERGE,
                    linkingPolicy: RelationshipLinkingPolicy::REFERENCE
                ),
            ]
        );

        $relationships = [
            'tags' => [
                'data' => [
                    ['type' => 'tags', 'id' => 'tag-4'],
                    ['type' => 'tags', 'id' => 'tag-5'],
                ],
            ],
        ];

        $this->resolver->applyRelationships($book, $relationships, $metadata, false);
        $this->em->flush();
        $this->em->clear();

        // Verify: both sides should be synchronized
        $book = $this->em->find(Book::class, 'book-10');
        $tag1 = $this->em->find(Tag::class, 'tag-4');
        $tag2 = $this->em->find(Tag::class, 'tag-5');

        $this->assertNotNull($book);
        $this->assertNotNull($tag1);
        $this->assertNotNull($tag2);

        // Owning side (book.tags)
        $this->assertCount(2, $book->getTags());
        $bookTagIds = array_map(fn(Tag $t) => $t->getId(), $book->getTags()->toArray());
        $this->assertContains('tag-4', $bookTagIds);
        $this->assertContains('tag-5', $bookTagIds);

        // Inverse side (tag.books)
        $this->assertTrue($tag1->getBooks()->contains($book), 'Tag 4 should contain book 10');
        $this->assertTrue($tag2->getBooks()->contains($book), 'Tag 5 should contain book 10');
    }

    /**
     * Test VERIFY policy: should throw ValidationException with proper JSON:API pointer when entity doesn't exist
     */
    public function testVerifyPolicyThrowsValidationExceptionForNonExistentEntity(): void
    {
        // Setup: Create author
        $author = new Author('author-6', 'Author Six');
        $this->em->persist($author);
        $this->em->flush();
        $this->em->clear();

        // Reload author
        $author = $this->em->find(Author::class, 'author-6');
        $this->assertNotNull($author);

        // Apply relationship: try to add non-existent book with VERIFY policy
        $metadata = new ResourceMetadata(
            type: 'authors',
            class: Author::class,
            attributes: [],
            relationships: [
                'books' => new RelationshipMetadata(
                    name: 'books',
                    toMany: true,
                    targetType: 'books',
                    targetClass: Book::class,
                    semantics: RelationshipSemantics::MERGE,
                    linkingPolicy: RelationshipLinkingPolicy::VERIFY // VERIFY policy
                ),
            ]
        );

        $relationships = [
            'books' => [
                'data' => [
                    ['type' => 'books', 'id' => 'non-existent-book'],
                ],
            ],
        ];

        try {
            $this->resolver->applyRelationships($author, $relationships, $metadata, false);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);

            $error = $errors[0];
            $this->assertEquals('422', $error->status);
            $this->assertStringContainsString('not found', $error->detail ?? '');
            // Verify pointer points to the specific element
            $this->assertNotNull($error->source);
            $this->assertStringContainsString('/data/relationships', $error->source->pointer ?? '');
        }
    }

    /**
     * Test minItems validation: should reject if collection has fewer items than minimum
     */
    public function testMinItemsValidationRejectsFewerItems(): void
    {
        // Setup: Create book
        $book = new Book('book-12', 'Book Twelve');
        $tag1 = new Tag('tag-6', 'Tag Six');

        $this->em->persist($book);
        $this->em->persist($tag1);
        $this->em->flush();
        $this->em->clear();

        // Reload book
        $book = $this->em->find(Book::class, 'book-12');
        $this->assertNotNull($book);

        // Apply relationship: try to set only 1 tag when minItems=2
        $metadata = new ResourceMetadata(
            type: 'books',
            class: Book::class,
            attributes: [],
            relationships: [
                'tags' => new RelationshipMetadata(
                    name: 'tags',
                    toMany: true,
                    targetType: 'tags',
                    targetClass: Tag::class,
                    semantics: RelationshipSemantics::MERGE,
                    linkingPolicy: RelationshipLinkingPolicy::REFERENCE,
                    minItems: 2 // Require at least 2 tags
                ),
            ]
        );

        $relationships = [
            'tags' => [
                'data' => [
                    ['type' => 'tags', 'id' => 'tag-6'],
                ],
            ],
        ];

        try {
            $this->resolver->applyRelationships($book, $relationships, $metadata, false);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);

            $error = $errors[0];
            $this->assertEquals('422', $error->status);
            $this->assertStringContainsString('at least 2', $error->detail ?? '');
            $this->assertNotNull($error->source);
            $this->assertEquals('/data/relationships/tags/data', $error->source->pointer);
        }
    }

    /**
     * Test nullable=false: should reject null for to-one relationships
     */
    public function testNullableFalseRejectsNullForToOne(): void
    {
        // Setup: Create book with author
        $author = new Author('author-8', 'Author Eight');
        $book = new Book('book-14', 'Book Fourteen');
        $author->addBook($book);

        $this->em->persist($author);
        $this->em->persist($book);
        $this->em->flush();
        $this->em->clear();

        // Reload book
        $book = $this->em->find(Book::class, 'book-14');
        $this->assertNotNull($book);

        // Apply relationship: try to set author to null when nullable=false
        $metadata = new ResourceMetadata(
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
                    nullable: false // Author is required
                ),
            ]
        );

        $relationships = [
            'author' => [
                'data' => null,
            ],
        ];

        try {
            $this->resolver->applyRelationships($book, $relationships, $metadata, false);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);

            $error = $errors[0];
            $this->assertEquals('422', $error->status);
            $this->assertStringContainsString('cannot be null', $error->detail ?? '');
            $this->assertNotNull($error->source);
            $this->assertEquals('/data/relationships/author/data', $error->source->pointer);
        }
    }
}

