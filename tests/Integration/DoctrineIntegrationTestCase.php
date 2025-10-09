<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use JsonApi\Symfony\Bridge\Doctrine\Flush\FlushManager;
use JsonApi\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use JsonApi\Symfony\Bridge\Doctrine\Persister\GenericDoctrineProcessor;
use JsonApi\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor;
use JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use JsonApi\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager;
use JsonApi\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use JsonApi\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use JsonApi\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use JsonApi\Symfony\Filter\Operator\Registry;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Http\Validation\DatabaseErrorMapper;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Resource\Relationship\RelationshipResolver;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Author;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Category;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Comment;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Product;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class DoctrineIntegrationTestCase extends TestCase
{
    protected EntityManagerInterface $em;
    protected ResourceRegistryInterface $registry;
    protected GenericDoctrineRepository $repository;
    protected GenericDoctrineProcessor $processor;
    protected ValidatingDoctrineProcessor $validatingProcessor;
    protected DoctrineTransactionManager $transactionManager;
    protected PropertyAccessorInterface $accessor;
    protected ValidatorInterface $validator;
    protected ConstraintViolationMapper $violationMapper;
    protected FlushManager $flushManager;

    /**
     * Returns the DSN used to connect to the database.
     * Each concrete test overrides this for its specific driver.
     */
    abstract protected function getDatabaseUrl(): string;

    /**
     * Returns the Doctrine database platform name.
     */
    abstract protected function getPlatform(): string;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the EntityManager
        $this->em = $this->createEntityManager();

        // Create the database schema
        $this->createSchema();

        // Initialise services
        $this->registry = new ResourceRegistry([
            Article::class,
            Author::class,
            Category::class,
            Comment::class,
            Tag::class,
            Product::class,
            User::class,
        ]);

        $this->accessor = PropertyAccess::createPropertyAccessor();

        // Create minimal dependencies for repository
        $operatorRegistry = new Registry([
            new \JsonApi\Symfony\Filter\Operator\EqualOperator(),
            new \JsonApi\Symfony\Filter\Operator\NotEqualOperator(),
            new \JsonApi\Symfony\Filter\Operator\LikeOperator(),
            new \JsonApi\Symfony\Filter\Operator\InOperator(),
            new \JsonApi\Symfony\Filter\Operator\NotInOperator(),
            new \JsonApi\Symfony\Filter\Operator\GreaterThanOperator(),
            new \JsonApi\Symfony\Filter\Operator\GreaterOrEqualOperator(),
            new \JsonApi\Symfony\Filter\Operator\LessThanOperator(),
            new \JsonApi\Symfony\Filter\Operator\LessOrEqualOperator(),
            new \JsonApi\Symfony\Filter\Operator\BetweenOperator(),
            new \JsonApi\Symfony\Filter\Operator\IsNullOperator(),
        ]);
        $filterHandlerRegistry = new FilterHandlerRegistry([]);
        $filterCompiler = new DoctrineFilterCompiler($operatorRegistry, $filterHandlerRegistry);
        $sortHandlerRegistry = new SortHandlerRegistry();

        $this->repository = new GenericDoctrineRepository(
            $this->em,
            $this->registry,
            $filterCompiler,
            $sortHandlerRegistry,
        );

        // Create SerializerEntityInstantiator
        $instantiator = new SerializerEntityInstantiator(
            $this->em,
            $this->accessor,
        );

        // Create FlushManager
        $this->flushManager = new FlushManager($this->em);

        $this->processor = new GenericDoctrineProcessor(
            $this->em,
            $this->registry,
            $this->accessor,
            $instantiator,
            $this->flushManager,
        );

        // Validator for ValidatingDoctrinePersister
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        // ConstraintViolationMapper needs an ErrorMapper
        // Provide a simplified version for tests
        $this->violationMapper = new ConstraintViolationMapper(
            $this->registry,
            new \JsonApi\Symfony\Http\Error\ErrorMapper(
                new \JsonApi\Symfony\Http\Error\ErrorBuilder(false)
            ),
        );

        // Create ErrorMapper and DatabaseErrorMapper
        $errorBuilder = new ErrorBuilder(true);
        $errorMapper = new ErrorMapper($errorBuilder);
        $databaseErrorMapper = new DatabaseErrorMapper(
            $this->registry,
            $errorMapper,
        );

        // Create RelationshipResolver
        $relationshipResolver = new RelationshipResolver(
            $this->em,
            $this->registry,
            $this->accessor,
        );

        $this->validatingProcessor = new ValidatingDoctrineProcessor(
            $this->em,
            $this->registry,
            $this->accessor,
            $this->validator,
            $this->violationMapper,
            $instantiator,
            $relationshipResolver,
            $this->flushManager,
        );

        $this->transactionManager = new DoctrineTransactionManager($this->em);
    }

    protected function tearDown(): void
    {
        // Clean up the database after each test
        if (isset($this->em) && $this->em->isOpen()) {
            $this->dropSchema();
            $this->em->close();
        }

        parent::tearDown();
    }

    private function createEntityManager(): EntityManagerInterface
    {
        // Use ArrayAdapter as cache in tests
        $cache = new ArrayAdapter();

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Fixtures/Entity'],
            isDevMode: true,
        );

        // Configure caches for metadata and queries
        $config->setMetadataCache($cache);
        $config->setQueryCache($cache);
        $config->setResultCache($cache);

        $connection = DriverManager::getConnection([
            'url' => $this->getDatabaseUrl(),
        ], $config);

        return new EntityManager($connection, $config);
    }

    private function createSchema(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        // Drop the schema first if it already exists
        $schemaTool->dropSchema($metadata);

        // Then create a fresh schema
        $schemaTool->createSchema($metadata);
    }

    private function dropSchema(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
    }

    /**
     * Seeds test data into the database.
     */
    protected function seedDatabase(): void
    {
        // Create authors
        $author1 = new Author();
        $author1->setId('author-1');
        $author1->setName('John Doe');
        $author1->setEmail('john@example.com');

        $author2 = new Author();
        $author2->setId('author-2');
        $author2->setName('Jane Smith');
        $author2->setEmail('jane@example.com');

        // Create tags
        $tag1 = new Tag();
        $tag1->setId('tag-1');
        $tag1->setName('PHP');

        $tag2 = new Tag();
        $tag2->setId('tag-2');
        $tag2->setName('Symfony');

        // Create articles
        $article1 = new Article();
        $article1->setId('article-1');
        $article1->setTitle('First Article');
        $article1->setContent('Content of first article');
        $article1->setAuthor($author1);
        $article1->addTag($tag1);
        $article1->addTag($tag2);

        $article2 = new Article();
        $article2->setId('article-2');
        $article2->setTitle('Second Article');
        $article2->setContent('Content of second article');
        $article2->setAuthor($author2);
        $article2->addTag($tag1);

        // Persist to the database
        $this->em->persist($author1);
        $this->em->persist($author2);
        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($article1);
        $this->em->persist($article2);
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * Clears all data from the database.
     */
    protected function clearDatabase(): void
    {
        $connection = $this->em->getConnection();

        // Disable foreign key checks
        $platform = $connection->getDatabasePlatform()->getName();

        if ($platform === 'postgresql') {
            $connection->executeStatement('TRUNCATE TABLE articles, authors, tags, article_tags, categories, comments, products, users RESTART IDENTITY CASCADE');
        } elseif ($platform === 'mysql') {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            $connection->executeStatement('TRUNCATE TABLE articles');
            $connection->executeStatement('TRUNCATE TABLE authors');
            $connection->executeStatement('TRUNCATE TABLE tags');
            $connection->executeStatement('TRUNCATE TABLE article_tags');
            $connection->executeStatement('TRUNCATE TABLE categories');
            $connection->executeStatement('TRUNCATE TABLE comments');
            $connection->executeStatement('TRUNCATE TABLE products');
            $connection->executeStatement('TRUNCATE TABLE users');
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        } elseif ($platform === 'sqlite') {
            $connection->executeStatement('DELETE FROM articles');
            $connection->executeStatement('DELETE FROM authors');
            $connection->executeStatement('DELETE FROM tags');
            $connection->executeStatement('DELETE FROM article_tags');
            $connection->executeStatement('DELETE FROM categories');
            $connection->executeStatement('DELETE FROM comments');
            $connection->executeStatement('DELETE FROM products');
            $connection->executeStatement('DELETE FROM users');
        }

        $this->em->clear();
    }

    /**
     * Helper method to flush changes in tests.
     *
     * Tests that use processor methods need to call this to persist changes to the database.
     * This mimics what WriteListener does in production.
     */
    protected function flush(): void
    {
        $this->flushManager->flush();
    }
}
