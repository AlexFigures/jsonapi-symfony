<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\ValidationGroups;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Http\Exception\ValidationException;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ValidatedArticle;

/**
 * Tests for validation groups during create operations.
 *
 * @group integration
 * @group validation-groups
 */
final class CreateOperationGroupsTest extends ValidationGroupsIntegrationTestCase
{
    public function testCreateWithDefaultValidationGroups(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        self::assertInstanceOf(ValidatedArticle::class, $article);
        self::assertSame('Valid Title', $article->getTitle());
        self::assertSame('Valid content that is long enough', $article->getContent());
    }

    public function testCreateIgnoresUpdateOnlyConstraints(): void
    {
        // contactEmail has Email constraint only in 'update' group
        // Should be ignored during create
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'contactEmail' => 'not-an-email', // Invalid email, but should be ignored on create
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        self::assertInstanceOf(ValidatedArticle::class, $article);
        self::assertSame('not-an-email', $article->getContactEmail());
    }





    public function testCreateValidationErrorsWithDefaultGroup(): void
    {
        // title is required in 'Default' group
        $changes = new ChangeSet(
            attributes: [
                'title' => '', // Empty title violates NotBlank in Default group
                'content' => 'Valid content that is long enough',
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertValidationErrorPointer($e, '/data/attributes/title');
            $this->assertValidationErrorDetail($e, '/blank|empty/i');
        }
    }



    public function testCreateWithRequiredFieldMissing(): void
    {
        // ValidatedArticle requires 'title' in constructor
        $changes = new ChangeSet(
            attributes: [
                'content' => 'Valid content that is long enough',
                // Missing 'title' - required constructor argument
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertValidationErrorPointer($e, '/data/attributes/title');
            $this->assertValidationErrorDetail($e, '/required/i');
        }
    }

    public function testCreateWithRelationshipsAndValidation(): void
    {
        // Create related entities
        $author = $this->createUser('author1', 'author@example.com');
        $category = $this->createCategory('Technology');

        // Test: Create with valid relationships
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Article with relationships',
                'content' => 'Content that is long enough for validation',
            ],
            relationships: [
                'author' => ['data' => ['type' => 'users', 'id' => $author->getId()]],
                'category' => ['data' => ['type' => 'categories', 'id' => $category->getId()]],
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);
        self::assertNotNull($article->getAuthor());
        self::assertEquals($author->getId(), $article->getAuthor()->getId());
        self::assertNotNull($article->getCategory());
        self::assertEquals($category->getId(), $article->getCategory()->getId());
    }

    public function testCreateWithValidPriority(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'priority' => 5, // Valid: between 1 and 10
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        self::assertSame(5, $article->getPriority());
    }

    public function testCreateWithInvalidPriority(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'priority' => 15, // Invalid: max is 10
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertValidationErrorPointer($e, '/data/attributes/priority');
            $this->assertValidationErrorDetail($e, '/range|10/i');
        }
    }


}
