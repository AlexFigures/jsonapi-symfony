<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\ValidationGroups;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Http\Exception\ValidationException;

/**
 * Tests for edge cases in validation and denormalization.
 *
 * @group integration
 * @group validation-groups
 * @group edge-cases
 */
final class EdgeCasesTest extends ValidationGroupsIntegrationTestCase
{
    public function testNoValidationGroupsInMetadata(): void
    {
        // Default OperationGroups should be used
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        self::assertSame('Valid Title', $article->getTitle());
    }

    public function testValidationWithNoConstraints(): void
    {
        // Fields without constraints should pass validation
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'status' => 'any-value', // No constraints in default groups
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        self::assertSame('any-value', $article->getStatus());
    }

    public function testDenormalizationWithEmptyAttributes(): void
    {
        // Empty attributes should not cause errors (but validation might fail)
        $changes = new ChangeSet(attributes: []);

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException for missing required field');
        } catch (ValidationException $e) {
            // Should fail because 'title' is required
            $this->assertValidationErrorPointer($e, '/data/attributes/title');
        }
    }

    public function testValidationAfterDenormalizationErrors(): void
    {
        // Denormalization errors should be thrown before validation
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'unknownField' => 'value', // Denormalization error
                'priority' => 999, // Would be validation error
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            // Should have both denormalization and validation errors
            $errors = $e->getErrors();
            self::assertGreaterThanOrEqual(1, count($errors));
        }
    }

    public function testMissingConstructorArgumentsHandling(): void
    {
        // ValidatedArticle requires 'title' in constructor
        $changes = new ChangeSet(
            attributes: [
                'content' => 'Valid content that is long enough',
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

    public function testPartialDenormalizationException(): void
    {
        // Multiple denormalization errors should all be collected
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'priority' => 'not-a-number', // Type error
                'publishedAt' => 'invalid-date', // Format error
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertGreaterThanOrEqual(2, count($errors));
        }
    }

    public function testDeepObjectToPopulateForEmbeddables(): void
    {
        // Test that OBJECT_TO_POPULATE works correctly with embeddable objects
        // Create article with initial contact info
        $article = $this->createValidatedArticle([
            'title' => 'Article with contact',
            'content' => 'Content that is long enough',
            'contactInfo' => [
                'email' => 'initial@example.com',
                'phone' => '1234567890',
            ],
        ]);

        self::assertNotNull($article->getContactInfo());
        self::assertEquals('initial@example.com', $article->getContactInfo()->getEmail());
        self::assertEquals('1234567890', $article->getContactInfo()->getPhone());

        // Update only the email, phone should remain unchanged
        $updated = $this->updateValidatedArticle($article->getId(), [
            'contactInfo' => [
                'email' => 'updated@example.com',
            ],
        ]);

        self::assertNotNull($updated->getContactInfo());
        self::assertEquals('updated@example.com', $updated->getContactInfo()->getEmail());
        self::assertEquals('1234567890', $updated->getContactInfo()->getPhone());
    }

    public function testUpdateWithNoChanges(): void
    {
        $article = $this->createValidatedArticle([
            'title' => 'Original Title',
            'content' => 'Original content that is long enough',
        ]);

        $id = $article->getId();

        // Update with empty attributes
        $changes = new ChangeSet(attributes: []);

        $updated = $this->validatingProcessor->processUpdate('validated-articles', $id, $changes);

        // Should succeed and preserve all values
        self::assertSame('Original Title', $updated->getTitle());
        self::assertSame('Original content that is long enough', $updated->getContent());
    }

    public function testCreateWithAllOptionalFields(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Minimal Article',
                'content' => 'Minimal content that is long enough',
                // All other fields are optional
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        self::assertSame('Minimal Article', $article->getTitle());
        self::assertSame('Minimal content that is long enough', $article->getContent());
        self::assertNull($article->getContactEmail());
        self::assertNull($article->getPriority());
        self::assertNull($article->getPublishedAt());
    }

    public function testUpdateWithNullValues(): void
    {
        $article = $this->createValidatedArticle([
            'title' => 'Original Title',
            'content' => 'Original content that is long enough',
            'priority' => 5,
            'contactEmail' => 'test@example.com',
        ]);

        $id = $article->getId();

        // Set nullable fields to null
        $changes = new ChangeSet(
            attributes: [
                'priority' => null,
                'contactEmail' => null,
            ]
        );

        $updated = $this->validatingProcessor->processUpdate('validated-articles', $id, $changes);

        self::assertNull($updated->getPriority());
        self::assertNull($updated->getContactEmail());
    }

    public function testBoundaryValuesForRangeConstraint(): void
    {
        // Test minimum boundary
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'priority' => 1, // Minimum valid value
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);
        self::assertSame(1, $article->getPriority());

        // Test maximum boundary
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title 2',
                'content' => 'Valid content that is long enough',
                'priority' => 10, // Maximum valid value
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);
        self::assertSame(10, $article->getPriority());
    }

    public function testBoundaryValuesForLengthConstraint(): void
    {
        // Test minimum length
        $changes = new ChangeSet(
            attributes: [
                'title' => 'ABC', // Exactly 3 characters (minimum)
                'content' => '1234567890', // Exactly 10 characters (minimum)
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);
        self::assertSame('ABC', $article->getTitle());
        self::assertSame('1234567890', $article->getContent());
    }
}
