<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\ValidationGroups;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Http\Exception\ValidationException;

/**
 * Tests for validation groups during update operations.
 *
 * @group integration
 * @group validation-groups
 */
final class UpdateOperationGroupsTest extends ValidationGroupsIntegrationTestCase
{
    public function testUpdateWithDefaultValidationGroups(): void
    {
        $article = $this->createValidatedArticle([
            'title' => 'Original Title',
            'content' => 'Original content that is long enough',
        ]);

        $id = $article->getId();

        $changes = new ChangeSet(
            attributes: [
                'title' => 'Updated Title',
            ]
        );

        $updated = $this->validatingProcessor->processUpdate('validated-articles', $id, $changes);

        self::assertSame('Updated Title', $updated->getTitle());
        self::assertSame('Original content that is long enough', $updated->getContent());
    }

    public function testUpdateIgnoresCreateOnlyConstraints(): void
    {
        $article = $this->createValidatedArticle([
            'title' => 'Original Title',
            'content' => 'Original content that is long enough',
        ]);

        $id = $article->getId();

        // content is required in 'create' group but not in 'update' group
        // Update without content should succeed
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Updated Title',
                // No 'content' - but that's OK for update
            ]
        );

        $updated = $this->validatingProcessor->processUpdate('validated-articles', $id, $changes);

        self::assertSame('Updated Title', $updated->getTitle());
        self::assertSame('Original content that is long enough', $updated->getContent());
    }





    public function testUpdatePartialAttributesPreservesOthers(): void
    {
        $article = $this->createValidatedArticle([
            'title' => 'Original Title',
            'content' => 'Original content that is long enough',
            'priority' => 5,
        ]);

        $id = $article->getId();

        // Update only title
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Updated Title',
            ]
        );

        $updated = $this->validatingProcessor->processUpdate('validated-articles', $id, $changes);

        self::assertSame('Updated Title', $updated->getTitle());
        self::assertSame('Original content that is long enough', $updated->getContent());
        self::assertSame(5, $updated->getPriority());
    }



    public function testUpdateWithRelationshipsAndValidation(): void
    {
        // Create related entities
        $author1 = $this->createUser('author1', 'author1@example.com');
        $author2 = $this->createUser('author2', 'author2@example.com');
        $category = $this->createCategory('Technology');

        // Create article with initial relationships
        $article = $this->createValidatedArticle([
            'title' => 'Original Title',
            'content' => 'Original content that is long enough',
        ]);

        // Update relationships
        $changes = new ChangeSet(
            relationships: [
                'author' => ['data' => ['type' => 'users', 'id' => $author1->getId()]],
                'category' => ['data' => ['type' => 'categories', 'id' => $category->getId()]],
            ]
        );

        $updated = $this->validatingProcessor->processUpdate('validated-articles', $article->getId(), $changes);
        self::assertNotNull($updated->getAuthor());
        self::assertEquals($author1->getId(), $updated->getAuthor()->getId());
        self::assertNotNull($updated->getCategory());
        self::assertEquals($category->getId(), $updated->getCategory()->getId());

        // Update to different author
        $changes = new ChangeSet(
            relationships: [
                'author' => ['data' => ['type' => 'users', 'id' => $author2->getId()]],
            ]
        );

        $updated = $this->validatingProcessor->processUpdate('validated-articles', $article->getId(), $changes);
        self::assertEquals($author2->getId(), $updated->getAuthor()->getId());
    }

    public function testUpdateNullToOneRelationshipWithValidation(): void
    {
        // Create related entities
        $author = $this->createUser('author1', 'author1@example.com');
        $category = $this->createCategory('Technology');

        // Create article with relationships
        $article = $this->createValidatedArticle([
            'title' => 'Original Title',
            'content' => 'Original content that is long enough',
        ]);

        // Set relationships
        $changes = new ChangeSet(
            relationships: [
                'author' => ['data' => ['type' => 'users', 'id' => $author->getId()]],
                'category' => ['data' => ['type' => 'categories', 'id' => $category->getId()]],
            ]
        );
        $this->validatingProcessor->processUpdate('validated-articles', $article->getId(), $changes);

        // Set category to null (should be allowed)
        $changes = new ChangeSet(
            relationships: [
                'category' => ['data' => null],
            ]
        );

        $updated = $this->validatingProcessor->processUpdate('validated-articles', $article->getId(), $changes);
        self::assertNull($updated->getCategory());
        self::assertNotNull($updated->getAuthor()); // Author should remain unchanged
    }

    public function testUpdateWithValidContactEmail(): void
    {
        $article = $this->createValidatedArticle([
            'title' => 'Original Title',
            'content' => 'Original content that is long enough',
        ]);

        $id = $article->getId();

        $changes = new ChangeSet(
            attributes: [
                'contactEmail' => 'valid@example.com',
            ]
        );

        $updated = $this->validatingProcessor->processUpdate('validated-articles', $id, $changes);

        self::assertSame('valid@example.com', $updated->getContactEmail());
    }



    public function testUpdateWithEmptyTitle(): void
    {
        $article = $this->createValidatedArticle([
            'title' => 'Original Title',
            'content' => 'Original content that is long enough',
        ]);

        $id = $article->getId();

        // title is required in 'Default' group (applies to both create and update)
        $changes = new ChangeSet(
            attributes: [
                'title' => '',
            ]
        );

        try {
            $this->validatingProcessor->processUpdate('validated-articles', $id, $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertValidationErrorPointer($e, '/data/attributes/title');
            $this->assertValidationErrorDetail($e, '/blank|empty/i');
        }
    }
}
