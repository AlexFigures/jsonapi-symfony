<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use JsonApi\Symfony\Bridge\Doctrine\Persister\GenericDoctrinePersister;
use JsonApi\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrinePersister;
use JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use JsonApi\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Article;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Author;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Product;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class DoctrineIntegrationTestCase extends TestCase
{
    protected EntityManagerInterface $em;
    protected ResourceRegistryInterface $registry;
    protected GenericDoctrineRepository $repository;
    protected GenericDoctrinePersister $persister;
    protected ValidatingDoctrinePersister $validatingPersister;
    protected DoctrineTransactionManager $transactionManager;
    protected PropertyAccessorInterface $accessor;
    protected ValidatorInterface $validator;
    protected ConstraintViolationMapper $violationMapper;

    /**
     * Возвращает DSN для подключения к БД.
     * Переопределяется в конкретных тестах для разных СУБД.
     */
    abstract protected function getDatabaseUrl(): string;

    /**
     * Возвращает платформу БД для Doctrine.
     */
    abstract protected function getPlatform(): string;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем EntityManager
        $this->em = $this->createEntityManager();

        // Создаем схему БД
        $this->createSchema();

        // Инициализируем сервисы
        $this->registry = new ResourceRegistry([
            Article::class,
            Author::class,
            Tag::class,
            Product::class,
            User::class,
        ]);

        $this->accessor = PropertyAccess::createPropertyAccessor();

        $this->repository = new GenericDoctrineRepository(
            $this->em,
            $this->registry,
        );

        $this->persister = new GenericDoctrinePersister(
            $this->em,
            $this->registry,
            $this->accessor,
        );

        // Validator для ValidatingDoctrinePersister
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        // Нужен ErrorMapper для ConstraintViolationMapper
        // Для тестов создаём упрощённую версию
        $this->violationMapper = new ConstraintViolationMapper(
            $this->registry,
            new \JsonApi\Symfony\Http\Error\ErrorMapper(
                new \JsonApi\Symfony\Http\Error\ErrorBuilder(false)
            ),
        );

        $this->validatingPersister = new ValidatingDoctrinePersister(
            $this->em,
            $this->registry,
            $this->accessor,
            $this->validator,
            $this->violationMapper,
        );

        $this->transactionManager = new DoctrineTransactionManager($this->em);
    }

    protected function tearDown(): void
    {
        // Очищаем БД после каждого теста
        if (isset($this->em) && $this->em->isOpen()) {
            $this->dropSchema();
            $this->em->close();
        }

        parent::tearDown();
    }

    private function createEntityManager(): EntityManagerInterface
    {
        // Используем ArrayAdapter для кеша в тестах
        $cache = new ArrayAdapter();

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Fixtures/Entity'],
            isDevMode: true,
        );

        // Устанавливаем кеш для метаданных и запросов
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

        // Сначала удаляем схему, если она существует
        $schemaTool->dropSchema($metadata);

        // Затем создаем новую схему
        $schemaTool->createSchema($metadata);
    }

    private function dropSchema(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
    }

    /**
     * Создает тестовые данные в БД.
     */
    protected function seedDatabase(): void
    {
        // Создаем авторов
        $author1 = new Author();
        $author1->setId('author-1');
        $author1->setName('John Doe');
        $author1->setEmail('john@example.com');

        $author2 = new Author();
        $author2->setId('author-2');
        $author2->setName('Jane Smith');
        $author2->setEmail('jane@example.com');

        // Создаем теги
        $tag1 = new Tag();
        $tag1->setId('tag-1');
        $tag1->setName('PHP');

        $tag2 = new Tag();
        $tag2->setId('tag-2');
        $tag2->setName('Symfony');

        // Создаем статьи
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

        // Сохраняем в БД
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
     * Очищает все данные из БД.
     */
    protected function clearDatabase(): void
    {
        $connection = $this->em->getConnection();

        // Отключаем проверку внешних ключей
        $platform = $connection->getDatabasePlatform()->getName();

        if ($platform === 'postgresql') {
            $connection->executeStatement('TRUNCATE TABLE articles, authors, tags, article_tags RESTART IDENTITY CASCADE');
        } elseif ($platform === 'mysql') {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            $connection->executeStatement('TRUNCATE TABLE articles');
            $connection->executeStatement('TRUNCATE TABLE authors');
            $connection->executeStatement('TRUNCATE TABLE tags');
            $connection->executeStatement('TRUNCATE TABLE article_tags');
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        } elseif ($platform === 'sqlite') {
            $connection->executeStatement('DELETE FROM articles');
            $connection->executeStatement('DELETE FROM authors');
            $connection->executeStatement('DELETE FROM tags');
            $connection->executeStatement('DELETE FROM article_tags');
        }

        $this->em->clear();
    }
}

