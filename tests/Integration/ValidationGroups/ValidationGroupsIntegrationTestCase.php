<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\ValidationGroups;

use AlexFigures\JsonApi\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor;
use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Http\Exception\ValidationException;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistry;
use AlexFigures\JsonApi\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Category;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\CategorySynonym;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Comment;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Product;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Tag;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\User;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\ValidatedArticle;

/**
 * Base test case for validation and denormalization groups integration tests.
 */
abstract class ValidationGroupsIntegrationTestCase extends DoctrineIntegrationTestCase
{
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

        // Override registry to include ValidatedArticle
        $this->registry = new ResourceRegistry([
            Article::class,
            Author::class,
            Category::class,
            CategorySynonym::class,
            Comment::class,
            Tag::class,
            Product::class,
            User::class,
            ValidatedArticle::class, // Add our test entity
        ]);

        // Recreate violationMapper with updated registry
        $this->violationMapper = new \AlexFigures\JsonApi\Http\Validation\ConstraintViolationMapper(
            $this->registry,
            new \AlexFigures\JsonApi\Http\Error\ErrorMapper(
                new \AlexFigures\JsonApi\Http\Error\ErrorBuilder(false)
            ),
        );

        // Recreate validatingProcessor with updated registry and violationMapper
        $this->validatingProcessor = new ValidatingDoctrineProcessor(
            $this->managerRegistry,
            $this->registry,
            $this->accessor,
            $this->validator,
            $this->violationMapper,
            new \AlexFigures\JsonApi\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator(
                $this->managerRegistry,
                $this->accessor,
            ),
            new \AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver(
                $this->managerRegistry,
                $this->registry,
                $this->accessor,
            ),
            $this->flushManager,
        );
    }

    /**
     * Create a ValidatedArticle entity for testing.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $relationships
     */
    protected function createValidatedArticle(array $attributes, array $relationships = []): ValidatedArticle
    {
        $changes = new ChangeSet(attributes: $attributes, relationships: $relationships);
        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        $this->em->flush();
        $this->em->clear();

        return $article;
    }

    /**
     * Update a ValidatedArticle entity.
     *
     * @param array<string, mixed> $attributes
     */
    protected function updateValidatedArticle(string $id, array $attributes): ValidatedArticle
    {
        $changes = new ChangeSet(attributes: $attributes);
        $article = $this->validatingProcessor->processUpdate('validated-articles', $id, $changes);

        $this->em->flush();
        $this->em->clear();

        return $article;
    }

    /**
     * Assert that a ValidationException contains an error with the given pointer.
     */
    protected function assertValidationErrorPointer(ValidationException $exception, string $expectedPointer): void
    {
        $errors = $exception->getErrors();
        $pointers = array_map(fn ($error) => $error->source?->pointer, $errors);

        self::assertContains(
            $expectedPointer,
            $pointers,
            sprintf(
                'Expected validation error with pointer "%s", but got: %s',
                $expectedPointer,
                implode(', ', array_filter($pointers))
            )
        );
    }

    /**
     * Assert that a ValidationException contains an error with detail matching the pattern.
     */
    protected function assertValidationErrorDetail(ValidationException $exception, string $pattern): void
    {
        $errors = $exception->getErrors();
        $details = array_map(fn ($error) => $error->detail ?? '', $errors);

        $found = false;
        foreach ($details as $detail) {
            if (preg_match($pattern, $detail)) {
                $found = true;
                break;
            }
        }

        self::assertTrue(
            $found,
            sprintf(
                'Expected validation error detail matching pattern "%s", but got: %s',
                $pattern,
                implode('; ', $details)
            )
        );
    }

    /**
     * Assert that a ValidationException contains exactly N errors.
     */
    protected function assertValidationErrorCount(ValidationException $exception, int $expectedCount): void
    {
        $errors = $exception->getErrors();
        self::assertCount(
            $expectedCount,
            $errors,
            sprintf('Expected %d validation errors, but got %d', $expectedCount, count($errors))
        );
    }

    /**
     * Create a User entity for testing relationships.
     */
    protected function createUser(string $username = 'testuser', string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setId(\Symfony\Component\Uid\Uuid::v7()->toString());
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword('password');
        $user->setSlug($username);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Create a Category entity for testing relationships.
     */
    protected function createCategory(string $name = 'Test Category'): Category
    {
        $category = new Category();
        $category->setName($name);

        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

}
