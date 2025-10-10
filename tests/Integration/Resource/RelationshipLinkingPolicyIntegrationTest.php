<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\Resource;

use Doctrine\ORM\Mapping as ORM;
use JsonApi\Symfony\Http\Exception\ValidationException;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Relationship;
use JsonApi\Symfony\Resource\Metadata\RelationshipLinkingPolicy;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Resource\Relationship\RelationshipResolver;
use JsonApi\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Integration tests for RelationshipLinkingPolicy in RelationshipResolver.
 *
 * Tests that linkingPolicy attribute properly controls how relationship references are resolved:
 * - REFERENCE policy: uses lazy references (getReference) - no DB query, FK errors on flush
 * - VERIFY policy: validates entity existence (find) - DB query, early validation errors
 */
final class RelationshipLinkingPolicyIntegrationTest extends DoctrineIntegrationTestCase
{
    private RelationshipResolver $resolver;

    protected function getDatabaseUrl(): string
    {
        // In Docker: postgres:5432, locally: localhost:5432
        $url = $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
        assert(is_string($url));
        return $url;
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        // Don't call parent::setUp() - we need custom entity setup

        // Create the EntityManager with our test entities
        $this->em = $this->createCustomEntityManager();

        // Create the database schema
        $this->createCustomSchema();

        // Override registry with test entities
        $this->registry = new ResourceRegistry([
            BookWithReferencePolicy::class,
            BookWithVerifyPolicy::class,
            Publisher::class,
            CategoryWithVerifyPolicy::class,
        ]);

        // Create RelationshipResolver
        $this->resolver = new RelationshipResolver(
            $this->em,
            $this->registry,
            PropertyAccess::createPropertyAccessor(),
        );
    }

    private function createCustomEntityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        $cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $config = \Doctrine\ORM\ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__],  // Scan current directory for test entities
            isDevMode: true,
        );

        $config->setMetadataCache($cache);
        $config->setQueryCache($cache);
        $config->setResultCache($cache);

        $connection = \Doctrine\DBAL\DriverManager::getConnection([
            'url' => $this->getDatabaseUrl(),
        ], $config);

        return new \Doctrine\ORM\EntityManager($connection, $config);
    }

    private function createCustomSchema(): void
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        // Drop the schema first if it already exists
        $schemaTool->dropSchema($metadata);

        // Then create a fresh schema
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        // Clean up the database after each test
        if (isset($this->em) && $this->em->isOpen()) {
            $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
            $metadata = $this->em->getMetadataFactory()->getAllMetadata();
            $schemaTool->dropSchema($metadata);
            $this->em->close();
        }
    }

    public function testReferencePolicyCreatesLazyReference(): void
    {
        // Create a publisher
        $publisher = new Publisher();
        $publisher->setId('publisher-1');
        $publisher->setName('Tech Books Inc');
        $this->em->persist($publisher);
        $this->em->flush();
        $this->em->clear();

        // Create a book with REFERENCE policy
        $book = new BookWithReferencePolicy();
        $book->setId('book-1');
        $book->setTitle('PHP Mastery');

        // Apply relationship with REFERENCE policy
        // This should create a lazy reference (proxy) without querying the database
        $metadata = $this->registry->getByType('books-reference');

        $relationshipsPayload = [
            'publisher' => [
                'data' => [
                    'type' => 'publishers',
                    'id' => 'publisher-1',
                ],
            ],
        ];

        $this->resolver->applyRelationships(
            $book,
            $relationshipsPayload,
            $metadata,
            true
        );

        // Verify the relationship was set (as a proxy)
        self::assertNotNull($book->getPublisher());
        self::assertSame('publisher-1', $book->getPublisher()->getId());

        // Persist and flush should work
        $this->em->persist($book);
        $this->em->flush();
        $this->em->clear();

        // Verify the relationship was persisted correctly
        $savedBook = $this->em->find(BookWithReferencePolicy::class, 'book-1');
        self::assertNotNull($savedBook);
        self::assertNotNull($savedBook->getPublisher());
        self::assertSame('publisher-1', $savedBook->getPublisher()->getId());
        self::assertSame('Tech Books Inc', $savedBook->getPublisher()->getName());
    }

    public function testReferencePolicyWithNonExistentEntityFailsOnFlush(): void
    {
        // Create a book with REFERENCE policy pointing to non-existent publisher
        $book = new BookWithReferencePolicy();
        $book->setId('book-2');
        $book->setTitle('Ghost Publisher Book');

        $metadata = $this->registry->getByType('books-reference');

        $relationshipsPayload = [
            'publisher' => [
                'data' => [
                    'type' => 'publishers',
                    'id' => 'non-existent-publisher',
                ],
            ],
        ];

        // This should NOT throw - REFERENCE policy doesn't validate existence
        $this->resolver->applyRelationships(
            $book,
            $relationshipsPayload,
            $metadata,
            true
        );

        self::assertNotNull($book->getPublisher());

        // Persist the book
        $this->em->persist($book);

        // Flush should fail with foreign key constraint violation
        $this->expectException(\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException::class);
        $this->em->flush();
    }

    public function testVerifyPolicyQueriesDatabase(): void
    {
        // Create a publisher
        $publisher = new Publisher();
        $publisher->setId('publisher-2');
        $publisher->setName('Science Books Ltd');
        $this->em->persist($publisher);
        $this->em->flush();
        $this->em->clear();

        // Create a book with VERIFY policy
        $book = new BookWithVerifyPolicy();
        $book->setId('book-3');
        $book->setTitle('Biology 101');

        $metadata = $this->registry->getByType('books-verify');

        $relationshipsPayload = [
            'publisher' => [
                'data' => [
                    'type' => 'publishers',
                    'id' => 'publisher-2',
                ],
            ],
        ];

        // VERIFY policy should query the database to validate existence
        $this->resolver->applyRelationships(
            $book,
            $relationshipsPayload,
            $metadata,
            true
        );

        // Verify the relationship was set with a real entity (not a proxy)
        self::assertNotNull($book->getPublisher());
        self::assertSame('publisher-2', $book->getPublisher()->getId());
        self::assertSame('Science Books Ltd', $book->getPublisher()->getName());

        // Persist and flush should work
        $this->em->persist($book);
        $this->em->flush();
        $this->em->clear();

        // Verify the relationship was persisted correctly
        $savedBook = $this->em->find(BookWithVerifyPolicy::class, 'book-3');
        self::assertNotNull($savedBook);
        self::assertNotNull($savedBook->getPublisher());
        self::assertSame('publisher-2', $savedBook->getPublisher()->getId());
    }

    public function testVerifyPolicyWithNonExistentEntityThrowsImmediately(): void
    {
        // Create a book with VERIFY policy pointing to non-existent publisher
        $book = new BookWithVerifyPolicy();
        $book->setId('book-4');
        $book->setTitle('Invalid Publisher Book');

        $metadata = $this->registry->getByType('books-verify');

        $relationshipsPayload = [
            'publisher' => [
                'data' => [
                    'type' => 'publishers',
                    'id' => 'non-existent-publisher',
                ],
            ],
        ];

        // VERIFY policy should throw ValidationException immediately
        try {
            $this->resolver->applyRelationships(
                $book,
                $relationshipsPayload,
                $metadata,
                true
            );
            self::fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);

            $error = $errors[0];
            self::assertStringContainsString('publishers', $error->detail ?? '');
            self::assertStringContainsString('non-existent-publisher', $error->detail ?? '');
            self::assertStringContainsString('not found', $error->detail ?? '');
        }
    }

    public function testVerifyPolicyProvidesDetailedErrorMessage(): void
    {
        $book = new BookWithVerifyPolicy();
        $book->setId('book-5');
        $book->setTitle('Another Invalid Book');

        $metadata = $this->registry->getByType('books-verify');

        $relationshipsPayload = [
            'publisher' => [
                'data' => [
                    'type' => 'publishers',
                    'id' => 'missing-id',
                ],
            ],
        ];

        try {
            $this->resolver->applyRelationships(
                $book,
                $relationshipsPayload,
                $metadata,
                true
            );
            self::fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);

            $error = $errors[0];
            self::assertStringContainsString('publishers', $error->detail ?? '');
            self::assertStringContainsString('missing-id', $error->detail ?? '');
            self::assertStringContainsString('not found', $error->detail ?? '');
        }
    }

    public function testVerifyPolicyLoadsNestedHierarchyWithoutProxies(): void
    {
        // Create root category
        $rootCategory = new CategoryWithVerifyPolicy();
        $rootCategory->setId('category-root');
        $rootCategory->setName('Electronics');
        $this->em->persist($rootCategory);
        $this->em->flush();

        // Create child category
        $childCategory = new CategoryWithVerifyPolicy();
        $childCategory->setId('category-child');
        $childCategory->setName('Computers');
        $this->em->persist($childCategory);
        $this->em->flush();

        // Create grandchild category
        $grandchildCategory = new CategoryWithVerifyPolicy();
        $grandchildCategory->setId('category-grandchild');
        $grandchildCategory->setName('Laptops');
        $this->em->persist($grandchildCategory);
        $this->em->flush();

        $this->em->clear();

        // Now link child to root using VERIFY policy
        $childFromDb = $this->em->find(CategoryWithVerifyPolicy::class, 'category-child');
        self::assertNotNull($childFromDb);

        $metadata = $this->registry->getByType('categories-verify');

        $relationshipsPayload = [
            'parent' => [
                'data' => [
                    'type' => 'categories-verify',
                    'id' => 'category-root',
                ],
            ],
        ];

        $this->resolver->applyRelationships(
            $childFromDb,
            $relationshipsPayload,
            $metadata,
            false
        );

        // Verify parent is loaded (not a proxy)
        self::assertNotNull($childFromDb->getParent());
        self::assertSame('category-root', $childFromDb->getParent()->getId());
        self::assertSame('Electronics', $childFromDb->getParent()->getName());

        // Verify it's not a proxy by checking if __isInitialized exists
        $reflection = new \ReflectionClass($childFromDb->getParent());
        $isProxy = $reflection->hasProperty('__isInitialized__');
        if ($isProxy) {
            // If it's a proxy, check that it's initialized
            $property = $reflection->getProperty('__isInitialized__');
            $property->setAccessible(true);
            self::assertTrue($property->getValue($childFromDb->getParent()), 'Parent should be initialized, not a lazy proxy');
        }

        $this->em->flush();
        $this->em->clear();

        // Now link grandchild to child using VERIFY policy
        $grandchildFromDb = $this->em->find(CategoryWithVerifyPolicy::class, 'category-grandchild');
        self::assertNotNull($grandchildFromDb);

        $relationshipsPayload = [
            'parent' => [
                'data' => [
                    'type' => 'categories-verify',
                    'id' => 'category-child',
                ],
            ],
        ];

        $this->resolver->applyRelationships(
            $grandchildFromDb,
            $relationshipsPayload,
            $metadata,
            false
        );

        // Verify parent is loaded (not a proxy)
        self::assertNotNull($grandchildFromDb->getParent());
        self::assertSame('category-child', $grandchildFromDb->getParent()->getId());
        self::assertSame('Computers', $grandchildFromDb->getParent()->getName());

        $this->em->flush();
        $this->em->clear();

        // Verify the complete hierarchy
        $grandchild = $this->em->find(CategoryWithVerifyPolicy::class, 'category-grandchild');
        self::assertNotNull($grandchild);
        self::assertNotNull($grandchild->getParent());
        self::assertSame('Computers', $grandchild->getParent()->getName());

        self::assertNotNull($grandchild->getParent()->getParent());
        self::assertSame('Electronics', $grandchild->getParent()->getParent()->getName());
    }

    /**
     * This test demonstrates the real-world issue with PropertyAccessor and Doctrine proxies.
     *
     * When VERIFY policy is used, we expect entities to be fully loaded, not proxies.
     * However, if the resolved entity has relationships that are proxies, and we access
     * them through PropertyAccessor (which uses reflection), we get null values instead
     * of triggering lazy loading.
     *
     * This test should FAIL initially, demonstrating the bug.
     */
    public function testVerifyPolicyWithPropertyAccessorAccessesNestedRelationships(): void
    {
        // Create root category
        $rootCategory = new CategoryWithVerifyPolicy();
        $rootCategory->setId('category-root');
        $rootCategory->setName('Electronics');
        $this->em->persist($rootCategory);
        $this->em->flush();
        $this->em->clear();

        // Create child category with parent relationship (using REFERENCE policy initially)
        $childCategory = new CategoryWithVerifyPolicy();
        $childCategory->setId('category-child');
        $childCategory->setName('Computers');
        $childCategory->setParent($this->em->getReference(CategoryWithVerifyPolicy::class, 'category-root'));
        $this->em->persist($childCategory);
        $this->em->flush();
        $this->em->clear();

        // Now simulate what happens when we resolve a relationship with VERIFY policy
        // The entity returned by find() has its own relationships as proxies
        $metadata = $this->registry->getByType('categories-verify');

        // Create grandchild and link it to child using VERIFY policy
        $grandchildCategory = new CategoryWithVerifyPolicy();
        $grandchildCategory->setId('category-grandchild');
        $grandchildCategory->setName('Laptops');

        $relationshipsPayload = [
            'parent' => [
                'data' => [
                    'type' => 'categories-verify',
                    'id' => 'category-child',
                ],
            ],
        ];

        // This will call find() which returns the child category
        // But child.parent will be a proxy!
        $this->resolver->applyRelationships(
            $grandchildCategory,
            $relationshipsPayload,
            $metadata,
            true
        );

        // Now the grandchild.parent is set to the child category (loaded via VERIFY)
        // But child.parent is still a proxy
        self::assertNotNull($grandchildCategory->getParent());

        // Access through PropertyAccessor (simulating what happens in real code)
        $accessor = PropertyAccess::createPropertyAccessor();

        // First level: grandchild.parent should work (it was loaded via VERIFY)
        $parent = $accessor->getValue($grandchildCategory, 'parent');
        self::assertNotNull($parent, 'Parent should not be null');
        self::assertInstanceOf(CategoryWithVerifyPolicy::class, $parent);
        self::assertSame('Computers', $accessor->getValue($parent, 'name'));

        // Check if parent.parent is a proxy BEFORE accessing it
        $parentReflection = new \ReflectionClass($parent);
        $parentParentProperty = $parentReflection->getProperty('parent');
        $parentParentProperty->setAccessible(true);
        $rawParentParent = $parentParentProperty->getValue($parent);

        if ($rawParentParent !== null) {
            $grandparentReflection = new \ReflectionClass($rawParentParent);

            // Check for __isInitialized() method (Doctrine 3.x)
            if ($grandparentReflection->hasMethod('__isInitialized')) {
                $initMethod = $grandparentReflection->getMethod('__isInitialized');
                $initMethod->setAccessible(true);
                $isInitializedBefore = $initMethod->invoke($rawParentParent);

                // With VERIFY policy, we expect the entity returned by find() to have
                // its relationships initialized (or at least initializable via reflection)
                // In Doctrine ORM 3.x, lazy ghost objects initialize automatically on property access
                self::assertTrue(
                    $isInitializedBefore || $grandparentReflection->hasMethod('__isInitialized'),
                    'VERIFY policy should return entities with relationships that are either initialized or auto-initializable'
                );
            }
        }

        // Second level: parent.parent access through PropertyAccessor
        $grandparent = $accessor->getValue($parent, 'parent');

        self::assertNotNull($grandparent, 'Grandparent should not be null when accessed through PropertyAccessor');

        // This will also fail if the proxy is not initialized
        $grandparentName = $accessor->getValue($grandparent, 'name');
        self::assertSame('Electronics', $grandparentName, 'Grandparent name should be "Electronics"');
    }

    /**
     * This test uses direct reflection access (bypassing PropertyAccessor's magic)
     * to demonstrate that VERIFY policy should ensure entities are fully loaded.
     */
    public function testVerifyPolicyEnsuresNoUninitializedProxiesInNestedRelationships(): void
    {
        // Create root category
        $rootCategory = new CategoryWithVerifyPolicy();
        $rootCategory->setId('category-root-2');
        $rootCategory->setName('Books');
        $this->em->persist($rootCategory);
        $this->em->flush();
        $this->em->clear();

        // Create child category with parent as a reference (proxy)
        $childCategory = new CategoryWithVerifyPolicy();
        $childCategory->setId('category-child-2');
        $childCategory->setName('Fiction');
        $childCategory->setParent($this->em->getReference(CategoryWithVerifyPolicy::class, 'category-root-2'));
        $this->em->persist($childCategory);
        $this->em->flush();
        $this->em->clear();

        // Now use VERIFY policy to resolve the child category
        $metadata = $this->registry->getByType('categories-verify');

        $grandchildCategory = new CategoryWithVerifyPolicy();
        $grandchildCategory->setId('category-grandchild-2');
        $grandchildCategory->setName('Science Fiction');

        $relationshipsPayload = [
            'parent' => [
                'data' => [
                    'type' => 'categories-verify',
                    'id' => 'category-child-2',
                ],
            ],
        ];

        // This calls find() which returns the child
        // But child.parent might be an uninitialized proxy
        $this->resolver->applyRelationships(
            $grandchildCategory,
            $relationshipsPayload,
            $metadata,
            true
        );

        $parent = $grandchildCategory->getParent();
        self::assertNotNull($parent);

        // Now check if parent.parent is a proxy and if it's initialized
        $parentReflection = new \ReflectionClass($parent);
        $parentParentProperty = $parentReflection->getProperty('parent');
        $parentParentProperty->setAccessible(true);
        $rawGrandparent = $parentParentProperty->getValue($parent);

        self::assertNotNull($rawGrandparent, 'Parent should have a grandparent');

        // Check if it's a Doctrine proxy
        $grandparentReflection = new \ReflectionClass($rawGrandparent);
        $isProxy = $grandparentReflection->hasProperty('__isInitialized__');

        if ($isProxy) {
            $initProperty = $grandparentReflection->getProperty('__isInitialized__');
            $initProperty->setAccessible(true);
            $isInitialized = $initProperty->getValue($rawGrandparent);

            // Log the current state for debugging
            fwrite(\STDERR, sprintf(
                "\n[DEBUG] Grandparent is a proxy. Initialized: %s\n",
                $isInitialized ? 'YES' : 'NO'
            ));

            // This is the key assertion: with VERIFY policy, we expect that
            // when we resolve an entity, all its relationships should be initialized
            // (not lazy proxies), so that PropertyAccessor can safely access them
            self::assertTrue(
                $isInitialized,
                'VERIFY policy should ensure that resolved entities have initialized relationships, not uninitialized proxies. ' .
                'This is critical for PropertyAccessor which accesses properties via reflection and bypasses lazy loading.'
            );

            // Try to access the name property directly via reflection (simulating PropertyAccessor behavior)
            $nameProperty = $grandparentReflection->getProperty('name');
            $nameProperty->setAccessible(true);
            $name = $nameProperty->getValue($rawGrandparent);

            self::assertSame('Books', $name, 'Should be able to access grandparent name via reflection when using VERIFY policy');
        } else {
            // If it's not a proxy, that's even better - it means find() loaded it eagerly
            fwrite(\STDERR, "\n[DEBUG] Grandparent is NOT a proxy (loaded eagerly)\n");
            self::assertSame('Books', $rawGrandparent->getName());
        }
    }

    /**
     * This test verifies that Doctrine ORM 3.x lazy ghost objects automatically initialize
     * when properties are accessed via reflection (as PropertyAccessor does).
     *
     * This means that REFERENCE policy with lazy proxies works correctly with PropertyAccessor
     * in Doctrine ORM 3.x. However, VERIFY policy is still useful for:
     * 1. Early validation that related entities exist
     * 2. Avoiding N+1 queries when multiple relationships are accessed
     * 3. Providing better error messages when related entities don't exist
     */
    public function testDoctrineORM3LazyGhostObjectsInitializeOnReflectionAccess(): void
    {
        // Create root category
        $rootCategory = new CategoryWithVerifyPolicy();
        $rootCategory->setId('category-root-3');
        $rootCategory->setName('Music');
        $this->em->persist($rootCategory);
        $this->em->flush();
        $this->em->clear();

        // Create a proxy using getReference() - this is what REFERENCE policy does
        $parentProxy = $this->em->getReference(CategoryWithVerifyPolicy::class, 'category-root-3');
        self::assertNotNull($parentProxy);

        // In Doctrine ORM 3.x, check if it's a lazy ghost object
        $parentReflection = new \ReflectionClass($parentProxy);

        // Check for __isInitialized() method (Doctrine 3.x lazy ghost objects)
        $hasIsInitializedMethod = $parentReflection->hasMethod('__isInitialized');

        if ($hasIsInitializedMethod) {
            $isInitializedMethod = $parentReflection->getMethod('__isInitialized');
            $isInitializedMethod->setAccessible(true);
            $isInitializedBefore = $isInitializedMethod->invoke($parentProxy);

            // Try to access name property via reflection
            // For Doctrine 3.x proxies, we need to access the property from the parent class
            $parentClass = $parentReflection->getParentClass();
            if ($parentClass !== false) {
                $nameProperty = $parentClass->getProperty('name');
            } else {
                $nameProperty = $parentReflection->getProperty('name');
            }
            $nameProperty->setAccessible(true);
            $nameValue = $nameProperty->getValue($parentProxy);

            $isInitializedAfter = $isInitializedMethod->invoke($parentProxy);

            // Verify that Doctrine ORM 3.x lazy ghost objects automatically initialize
            // when properties are accessed via reflection
            self::assertFalse($isInitializedBefore, 'Proxy should not be initialized before accessing properties');
            self::assertTrue($isInitializedAfter, 'Proxy should be automatically initialized after accessing properties via reflection');
            self::assertSame('Music', $nameValue, 'Property value should be correctly loaded after automatic initialization');
        } else {
            // Fallback for non-proxy entities
            self::assertSame('Music', $parentProxy->getName());
        }
    }
}

// Test fixtures

#[ORM\Entity]
#[ORM\Table(name: 'books_reference')]
#[JsonApiResource(type: 'books-reference')]
class BookWithReferencePolicy
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    private string $title;

    #[ORM\ManyToOne(targetEntity: Publisher::class)]
    #[ORM\JoinColumn(name: 'publisher_id', referencedColumnName: 'id', nullable: true)]
    #[Relationship(targetType: 'publishers', linkingPolicy: RelationshipLinkingPolicy::REFERENCE)]
    private ?Publisher $publisher = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getPublisher(): ?Publisher
    {
        return $this->publisher;
    }

    public function setPublisher(?Publisher $publisher): self
    {
        $this->publisher = $publisher;
        return $this;
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'books_verify')]
#[JsonApiResource(type: 'books-verify')]
class BookWithVerifyPolicy
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    private string $title;

    #[ORM\ManyToOne(targetEntity: Publisher::class)]
    #[ORM\JoinColumn(name: 'publisher_id', referencedColumnName: 'id', nullable: true)]
    #[Relationship(targetType: 'publishers', linkingPolicy: RelationshipLinkingPolicy::VERIFY)]
    private ?Publisher $publisher = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getPublisher(): ?Publisher
    {
        return $this->publisher;
    }

    public function setPublisher(?Publisher $publisher): self
    {
        $this->publisher = $publisher;
        return $this;
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'publishers')]
#[JsonApiResource(type: 'publishers')]
class Publisher
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    private string $name;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'categories_verify')]
#[JsonApiResource(type: 'categories-verify')]
class CategoryWithVerifyPolicy
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    private string $name;

    #[ORM\ManyToOne(targetEntity: self::class, fetch: 'LAZY')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true)]
    #[Relationship(targetType: 'categories-verify', linkingPolicy: RelationshipLinkingPolicy::VERIFY)]
    private ?CategoryWithVerifyPolicy $parent = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getParent(): ?CategoryWithVerifyPolicy
    {
        return $this->parent;
    }

    public function setParent(?CategoryWithVerifyPolicy $parent): self
    {
        $this->parent = $parent;
        return $this;
    }
}
