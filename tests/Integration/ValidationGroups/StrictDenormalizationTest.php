<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\ValidationGroups;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Http\Exception\ValidationException;

/**
 * Tests for strict denormalization mode (ALLOW_EXTRA_ATTRIBUTES=false).
 *
 * ValidatingDoctrineProcessor uses strict mode by default:
 * - ALLOW_EXTRA_ATTRIBUTES = false (reject unknown attributes)
 * - COLLECT_DENORMALIZATION_ERRORS = true (collect all errors)
 *
 * @group integration
 * @group validation-groups
 * @group strict-denormalization
 */
final class StrictDenormalizationTest extends ValidationGroupsIntegrationTestCase
{
    public function testStrictModeRejectsExtraAttributes(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'unknownField' => 'This should fail',
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException for unknown attribute');
        } catch (ValidationException $e) {
            $this->assertValidationErrorPointer($e, '/data/attributes/unknownField');
            $this->assertValidationErrorDetail($e, '/unknown|not allowed|extra/i');
        }
    }

    public function testStrictModeRejectsMultipleExtraAttributes(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'unknownField1' => 'First unknown',
                'unknownField2' => 'Second unknown',
                'unknownField3' => 'Third unknown',
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException for unknown attributes');
        } catch (ValidationException $e) {
            // Should collect all errors
            $this->assertValidationErrorCount($e, 3);
            $this->assertValidationErrorPointer($e, '/data/attributes/unknownField1');
            $this->assertValidationErrorPointer($e, '/data/attributes/unknownField2');
            $this->assertValidationErrorPointer($e, '/data/attributes/unknownField3');
        }
    }

    public function testStrictModeAllowsKnownAttributes(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'contactEmail' => 'test@example.com',
                'priority' => 5,
                'status' => 'draft',
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        self::assertSame('Valid Title', $article->getTitle());
        self::assertSame('Valid content that is long enough', $article->getContent());
        self::assertSame('test@example.com', $article->getContactEmail());
        self::assertSame(5, $article->getPriority());
        self::assertSame('draft', $article->getStatus());
    }

    public function testCollectDenormalizationErrors(): void
    {
        // COLLECT_DENORMALIZATION_ERRORS = true means all NotNormalizableValueException errors are collected
        // Note: ExtraAttributesException is thrown immediately and not collected
        // Note: When denormalization errors occur, validation is not performed
        // Note: TypeCoercingDenormalizer performs safe type coercion (int->string is safe)
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'publishedAt' => 'invalid-date', // Invalid date format -> NotNormalizableValueException
                'priority' => 'not-an-integer', // Invalid type -> NotNormalizableValueException
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            // Should have errors for: publishedAt format, priority type
            $errors = $e->getErrors();
            self::assertGreaterThanOrEqual(2, count($errors), 'Should collect multiple denormalization errors');
        }
    }

    public function testDenormalizationErrorsWithInvalidType(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'priority' => 'not-an-integer', // String instead of integer
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException for type error');
        } catch (ValidationException $e) {
            $this->assertValidationErrorPointer($e, '/data/attributes/priority');
            $this->assertValidationErrorDetail($e, '/type|integer/i');
        }
    }

    public function testDenormalizationErrorsWithInvalidFormat(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'publishedAt' => 'not-a-valid-date', // Invalid date format
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException for format error');
        } catch (ValidationException $e) {
            $this->assertValidationErrorPointer($e, '/data/attributes/publishedAt');
            $this->assertValidationErrorDetail($e, '/date|format|parse/i');
        }
    }

    public function testStrictModeWithRelationships(): void
    {
        // Strict mode should not affect relationships - they are handled separately
        // This test verifies that relationships can be set even with strict denormalization
        $author = $this->createUser('author1', 'author1@example.com');
        $category = $this->createCategory('Technology');

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
        self::assertNotNull($article->getCategory());
    }

    public function testStrictModeOnUpdate(): void
    {
        $article = $this->createValidatedArticle([
            'title' => 'Original Title',
            'content' => 'Original content that is long enough',
        ]);

        $id = $article->getId();

        $changes = new ChangeSet(
            attributes: [
                'title' => 'Updated Title',
                'unknownField' => 'This should fail on update too',
            ]
        );

        try {
            $this->validatingProcessor->processUpdate('validated-articles', $id, $changes);
            self::fail('Expected ValidationException for unknown attribute on update');
        } catch (ValidationException $e) {
            $this->assertValidationErrorPointer($e, '/data/attributes/unknownField');
            $this->assertValidationErrorDetail($e, '/unknown|not allowed|extra/i');
        }
    }

    public function testValidDateTimeFormat(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'publishedAt' => '2024-01-15T10:30:00+00:00', // Valid ISO 8601 format
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        self::assertInstanceOf(\DateTimeImmutable::class, $article->getPublishedAt());
        self::assertSame('2024-01-15', $article->getPublishedAt()->format('Y-m-d'));
    }

    public function testNullValuesAllowedForNullableFields(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'contactEmail' => null,
                'priority' => null,
                'publishedAt' => null,
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        self::assertNull($article->getContactEmail());
        self::assertNull($article->getPriority());
        self::assertNull($article->getPublishedAt());
    }
}
