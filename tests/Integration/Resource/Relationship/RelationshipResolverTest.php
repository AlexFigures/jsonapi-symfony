<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Resource\Relationship;

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
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $schemaTool->createSchema([
            $this->em->getClassMetadata(Author::class),
            $this->em->getClassMetadata(Book::class),
            $this->em->getClassMetadata(Tag::class),
            $this->em->getClassMetadata(Publisher::class),
        ]);

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
        $this->em->close();
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
     * Test MERGE semantics: keeps existing items and adds new ones (no removal)
     *
     * Note: MERGE semantics means "merge the provided items into the collection"
     * It does NOT remove items that are not in the payload - use REPLACE for that.
     */
    public function testMergeSemanticsKeepsExistingItemsAndAddsNew(): void
    {
        // Setup: Create author with 2 books
        $author = new Author('author-2', 'Jane Smith');
        $book1 = new Book('book-4', 'Book Four');
        $book2 = new Book('book-5', 'Book Five');
        $book3 = new Book('book-6', 'Book Six');

        $author->addBook($book1);
        $author->addBook($book2);

        $this->em->persist($author);
        $this->em->persist($book1);
        $this->em->persist($book2);
        $this->em->persist($book3);
        $this->em->flush();
        $this->em->clear();

        // Reload author
        $author = $this->em->find(Author::class, 'author-2');
        $this->assertNotNull($author);
        $this->assertCount(2, $author->getBooks());

        // Apply relationship: add book-6 using MERGE semantics
        // Note: book-4 and book-5 are already in the collection
        // MERGE will keep them and add book-6
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
                    ['type' => 'books', 'id' => 'book-4'],
                    ['type' => 'books', 'id' => 'book-6'], // New book
                    // book-5 is NOT in the payload, but MERGE keeps it
                ],
            ],
        ];

        $this->resolver->applyRelationships($author, $relationships, $metadata, false);
        $this->em->flush();
        $this->em->clear();

        // Verify: all 3 books should be present (MERGE doesn't remove)
        $author = $this->em->find(Author::class, 'author-2');
        $this->assertNotNull($author);
        $this->assertCount(3, $author->getBooks());

        $bookIds = array_map(fn(Book $b) => $b->getId(), $author->getBooks()->toArray());
        $this->assertContains('book-4', $bookIds);
        $this->assertContains('book-5', $bookIds, 'Book 5 should still be in collection (MERGE keeps existing)');
        $this->assertContains('book-6', $bookIds);
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
     * Test bidirectional to-one relationship: verify inverse side is updated
     */
    public function testBidirectionalToOneSynchronization(): void
    {
        // Setup: Create book and author
        $author1 = new Author('author-4', 'Author Four');
        $author2 = new Author('author-5', 'Author Five');
        $book = new Book('book-11', 'Book Eleven');

        $author1->addBook($book);

        $this->em->persist($author1);
        $this->em->persist($author2);
        $this->em->persist($book);
        $this->em->flush();
        $this->em->clear();

        // Reload book
        $book = $this->em->find(Book::class, 'book-11');
        $this->assertNotNull($book);
        $this->assertEquals('author-4', $book->getAuthor()?->getId());

        // Apply relationship: change author from author-4 to author-5
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
                    linkingPolicy: RelationshipLinkingPolicy::REFERENCE
                ),
            ]
        );

        $relationships = [
            'author' => [
                'data' => ['type' => 'authors', 'id' => 'author-5'],
            ],
        ];

        $this->resolver->applyRelationships($book, $relationships, $metadata, false);
        $this->em->flush();
        $this->em->clear();

        // Verify: both sides should be synchronized
        $book = $this->em->find(Book::class, 'book-11');
        $author1 = $this->em->find(Author::class, 'author-4');
        $author2 = $this->em->find(Author::class, 'author-5');

        $this->assertNotNull($book);
        $this->assertNotNull($author1);
        $this->assertNotNull($author2);

        // Owning side (book.author)
        $this->assertEquals('author-5', $book->getAuthor()?->getId());

        // Inverse side: author-4 should no longer have book-11
        $author1BookIds = array_map(fn(Book $b) => $b->getId(), $author1->getBooks()->toArray());
        $this->assertNotContains('book-11', $author1BookIds);

        // Inverse side: author-5 should now have book-11
        $author2BookIds = array_map(fn(Book $b) => $b->getId(), $author2->getBooks()->toArray());
        $this->assertContains('book-11', $author2BookIds);
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
     * Test REFERENCE policy: should use lazy getReference() without immediate validation
     *
     * Note: With REFERENCE policy, the entity is not validated immediately.
     * The error will occur later when Doctrine tries to access the proxy or persist it.
     */
    public function testReferencePolicyUsesLazyReference(): void
    {
        // Setup: Create author and a real book
        $author = new Author('author-7', 'Author Seven');
        $realBook = new Book('book-real', 'Real Book');

        $this->em->persist($author);
        $this->em->persist($realBook);
        $this->em->flush();
        $this->em->clear();

        // Reload author
        $author = $this->em->find(Author::class, 'author-7');
        $this->assertNotNull($author);

        // Apply relationship: add real book with REFERENCE policy
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
                    linkingPolicy: RelationshipLinkingPolicy::REFERENCE // REFERENCE policy
                ),
            ]
        );

        $relationships = [
            'books' => [
                'data' => [
                    ['type' => 'books', 'id' => 'book-real'],
                ],
            ],
        ];

        // Should NOT throw during applyRelationships (lazy reference)
        $this->resolver->applyRelationships($author, $relationships, $metadata, false);

        // Should successfully flush with valid reference
        $this->em->flush();
        $this->em->clear();

        // Verify the relationship was established
        $author = $this->em->find(Author::class, 'author-7');
        $this->assertNotNull($author);
        $this->assertCount(1, $author->getBooks());
        $this->assertEquals('book-real', $author->getBooks()->first()->getId());
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
     * Test maxItems validation: should reject if collection has more items than maximum
     */
    public function testMaxItemsValidationRejectsMoreItems(): void
    {
        // Setup: Create book and tags
        $book = new Book('book-13', 'Book Thirteen');
        $tag1 = new Tag('tag-7', 'Tag Seven');
        $tag2 = new Tag('tag-8', 'Tag Eight');
        $tag3 = new Tag('tag-9', 'Tag Nine');

        $this->em->persist($book);
        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($tag3);
        $this->em->flush();
        $this->em->clear();

        // Reload book
        $book = $this->em->find(Book::class, 'book-13');
        $this->assertNotNull($book);

        // Apply relationship: try to set 3 tags when maxItems=2
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
                    maxItems: 2 // Allow at most 2 tags
                ),
            ]
        );

        $relationships = [
            'tags' => [
                'data' => [
                    ['type' => 'tags', 'id' => 'tag-7'],
                    ['type' => 'tags', 'id' => 'tag-8'],
                    ['type' => 'tags', 'id' => 'tag-9'],
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
            $this->assertStringContainsString('at most 2', $error->detail ?? '');
            $this->assertNotNull($error->source);
            $this->assertEquals('/data/relationships/tags/data', $error->source->pointer);
        }
    }

    /**
     * Test nullable=false: should reject null for to-one relationships
     */
    public function testNullablefalseRejectsNullForToOne(): void
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

    /**
     * Test nullable=true: should accept null for to-one relationships
     */
    public function testNullableTrueAcceptsNullForToOne(): void
    {
        // Setup: Create book with author
        $author = new Author('author-9', 'Author Nine');
        $book = new Book('book-15', 'Book Fifteen');
        $author->addBook($book);

        $this->em->persist($author);
        $this->em->persist($book);
        $this->em->flush();
        $this->em->clear();

        // Reload book
        $book = $this->em->find(Book::class, 'book-15');
        $this->assertNotNull($book);
        $this->assertNotNull($book->getAuthor());

        // Apply relationship: set author to null when nullable=true
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
                    nullable: true // Author is optional
                ),
            ]
        );

        $relationships = [
            'author' => [
                'data' => null,
            ],
        ];

        $this->resolver->applyRelationships($book, $relationships, $metadata, false);
        $this->em->flush();
        $this->em->clear();

        // Verify: author should be null
        $book = $this->em->find(Book::class, 'book-15');
        $this->assertNotNull($book);
        $this->assertNull($book->getAuthor());
    }

    /**
     * Test targetType mismatch: should reject wrong resource type with proper error pointer
     */
    public function testTargetTypeMismatchRejectsWrongType(): void
    {
        // Setup: Create book
        $book = new Book('book-16', 'Book Sixteen');
        $this->em->persist($book);
        $this->em->flush();
        $this->em->clear();

        // Reload book
        $book = $this->em->find(Book::class, 'book-16');
        $this->assertNotNull($book);

        // Apply relationship: try to set wrong type (tags instead of authors)
        $metadata = new ResourceMetadata(
            type: 'books',
            class: Book::class,
            attributes: [],
            relationships: [
                'author' => new RelationshipMetadata(
                    name: 'author',
                    toMany: false,
                    targetType: 'authors', // Expect authors
                    targetClass: Author::class,
                    linkingPolicy: RelationshipLinkingPolicy::REFERENCE
                ),
            ]
        );

        $relationships = [
            'author' => [
                'data' => ['type' => 'tags', 'id' => 'tag-1'], // Wrong type!
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
            $this->assertStringContainsString('expected "authors"', $error->detail ?? '');
            $this->assertStringContainsString('got "tags"', $error->detail ?? '');
            $this->assertNotNull($error->source);
            $this->assertStringContainsString('/type', $error->source->pointer ?? '');
        }
    }

    /**
     * Test targetClass incompatibility: should reject when resolved class doesn't match expected class
     */
    public function testTargetClassIncompatibilityRejectsWrongClass(): void
    {
        // Setup: Create book
        $book = new Book('book-17', 'Book Seventeen');
        $publisher = new Publisher('pub-1', 'Publisher One');

        $this->em->persist($book);
        $this->em->persist($publisher);
        $this->em->flush();
        $this->em->clear();

        // Reload book
        $book = $this->em->find(Book::class, 'book-17');
        $this->assertNotNull($book);

        // Apply relationship: try to set publisher as author (incompatible classes)
        $metadata = new ResourceMetadata(
            type: 'books',
            class: Book::class,
            attributes: [],
            relationships: [
                'author' => new RelationshipMetadata(
                    name: 'author',
                    toMany: false,
                    targetType: 'publishers', // Type matches registry
                    targetClass: Author::class, // But class doesn't match
                    linkingPolicy: RelationshipLinkingPolicy::REFERENCE
                ),
            ]
        );

        $relationships = [
            'author' => [
                'data' => ['type' => 'publishers', 'id' => 'pub-1'],
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
            $this->assertStringContainsString('not compatible', $error->detail ?? '');
            $this->assertNotNull($error->source);
            $this->assertStringContainsString('/type', $error->source->pointer ?? '');
        }
    }

    /**
     * Test idempotency: adding the same item twice in a single request should handle gracefully
     */
    public function testIdempotencyHandlesDuplicatesGracefully(): void
    {
        // Setup: Create book and tag
        $book = new Book('book-18', 'Book Eighteen');
        $tag1 = new Tag('tag-10', 'Tag Ten');

        $this->em->persist($book);
        $this->em->persist($tag1);
        $this->em->flush();
        $this->em->clear();

        // Reload book
        $book = $this->em->find(Book::class, 'book-18');
        $this->assertNotNull($book);

        // Apply relationship: add same tag twice
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
                    ['type' => 'tags', 'id' => 'tag-10'],
                    ['type' => 'tags', 'id' => 'tag-10'], // Duplicate!
                ],
            ],
        ];

        $this->resolver->applyRelationships($book, $relationships, $metadata, false);
        $this->em->flush();
        $this->em->clear();

        // Verify: tag should only appear once
        $book = $this->em->find(Book::class, 'book-18');
        $this->assertNotNull($book);
        $this->assertCount(1, $book->getTags(), 'Duplicate should be handled, only one tag should exist');
        $this->assertEquals('tag-10', $book->getTags()->first()->getId());
    }

    /**
     * Test writableOnCreate=false: should reject relationship on POST
     */
    public function testWritableOnCreateFalseRejectsOnCreate(): void
    {
        // Setup: Create author
        $author = new Author('author-10', 'Author Ten');
        $book = new Book('book-19', 'Book Nineteen');

        $this->em->persist($author);
        $this->em->persist($book);
        $this->em->flush();

        // Apply relationship on CREATE with writableOnCreate=false
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
                    writableOnCreate: false, // Not writable on create
                    writableOnUpdate: true
                ),
            ]
        );

        $relationships = [
            'author' => [
                'data' => ['type' => 'authors', 'id' => 'author-10'],
            ],
        ];

        try {
            $this->resolver->applyRelationships($book, $relationships, $metadata, true); // isCreate=true
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);

            $error = $errors[0];
            $this->assertEquals('422', $error->status);
            $this->assertStringContainsString('not writable', $error->detail ?? '');
            $this->assertNotNull($error->source);
            $this->assertEquals('/data/relationships/author/data', $error->source->pointer);
        }
    }

    /**
     * Test writableOnUpdate=false: should reject relationship on PATCH
     */
    public function testWritableOnUpdateFalseRejectsOnUpdate(): void
    {
        // Setup: Create book with author
        $author1 = new Author('author-11', 'Author Eleven');
        $author2 = new Author('author-12', 'Author Twelve');
        $book = new Book('book-20', 'Book Twenty');
        $author1->addBook($book);

        $this->em->persist($author1);
        $this->em->persist($author2);
        $this->em->persist($book);
        $this->em->flush();
        $this->em->clear();

        // Reload book
        $book = $this->em->find(Book::class, 'book-20');
        $this->assertNotNull($book);

        // Apply relationship on UPDATE with writableOnUpdate=false
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
                    writableOnCreate: true,
                    writableOnUpdate: false // Not writable on update
                ),
            ]
        );

        $relationships = [
            'author' => [
                'data' => ['type' => 'authors', 'id' => 'author-12'],
            ],
        ];

        try {
            $this->resolver->applyRelationships($book, $relationships, $metadata, false); // isCreate=false
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);

            $error = $errors[0];
            $this->assertEquals('422', $error->status);
            $this->assertStringContainsString('not writable', $error->detail ?? '');
            $this->assertNotNull($error->source);
            $this->assertEquals('/data/relationships/author/data', $error->source->pointer);
        }
    }

    /**
     * Test writableOnCreate=true, writableOnUpdate=true: should accept on both operations
     */
    public function testWritableTrueAcceptsOnBothOperations(): void
    {
        // Setup: Create authors and book
        $author1 = new Author('author-13', 'Author Thirteen');
        $author2 = new Author('author-14', 'Author Fourteen');
        $book = new Book('book-21', 'Book Twenty One');

        $this->em->persist($author1);
        $this->em->persist($author2);
        $this->em->persist($book);
        $this->em->flush();
        $this->em->clear();

        // Test CREATE operation
        $book = $this->em->find(Book::class, 'book-21');
        $this->assertNotNull($book);

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
                    writableOnCreate: true,
                    writableOnUpdate: true
                ),
            ]
        );

        // Set author on create
        $relationships = [
            'author' => [
                'data' => ['type' => 'authors', 'id' => 'author-13'],
            ],
        ];

        $this->resolver->applyRelationships($book, $relationships, $metadata, true); // isCreate=true
        $this->em->flush();
        $this->em->clear();

        // Verify
        $book = $this->em->find(Book::class, 'book-21');
        $this->assertNotNull($book);
        $this->assertEquals('author-13', $book->getAuthor()?->getId());

        // Test UPDATE operation
        $relationships = [
            'author' => [
                'data' => ['type' => 'authors', 'id' => 'author-14'],
            ],
        ];

        $this->resolver->applyRelationships($book, $relationships, $metadata, false); // isCreate=false
        $this->em->flush();
        $this->em->clear();

        // Verify
        $book = $this->em->find(Book::class, 'book-21');
        $this->assertNotNull($book);
        $this->assertEquals('author-14', $book->getAuthor()?->getId());
    }
}

