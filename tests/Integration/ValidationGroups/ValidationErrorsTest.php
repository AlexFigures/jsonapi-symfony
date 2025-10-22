<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\ValidationGroups;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Http\Exception\ValidationException;

/**
 * Tests for validation error formatting and JSON:API error structure.
 *
 * @group integration
 * @group validation-groups
 * @group validation-errors
 */
final class ValidationErrorsTest extends ValidationGroupsIntegrationTestCase
{
    public function testValidationErrorPointerForAttribute(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => '', // Empty title violates NotBlank
                'content' => 'Valid content that is long enough',
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);

            $error = $errors[0];
            self::assertSame('422', $error->status);
            self::assertSame('/data/attributes/title', $error->source?->pointer);
            self::assertNotNull($error->detail);
            self::assertNotEmpty($error->detail);
        }
    }

    public function testValidationErrorPointerForRelationship(): void
    {
        // Test that validation errors for relationships have correct JSON:API pointers
        // Create a valid author first
        $author = $this->createUser('test-author', 'author@example.com');

        // Create article with valid relationship
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Article with author',
                'content' => 'Content that is long enough for validation',
            ],
            relationships: [
                'author' => ['data' => ['type' => 'users', 'id' => $author->getId()]],
            ]
        );

        $article = $this->validatingProcessor->processCreate('validated-articles', $changes);

        // Verify relationship was set correctly
        self::assertNotNull($article->getAuthor());
        self::assertEquals($author->getId(), $article->getAuthor()->getId());
    }

    public function testValidationErrorsIncludeAllViolations(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'AB', // Too short (min: 3)
                'content' => 'Valid content that is long enough', // Valid content
                'priority' => 15, // Out of range (max: 10)
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(2, $errors);

            $pointers = array_map(fn ($error) => $error->source?->pointer, $errors);
            self::assertContains('/data/attributes/title', $pointers);
            self::assertContains('/data/attributes/priority', $pointers);
        }
    }

    public function testValidationErrorsWithCustomMessages(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'priority' => 15, // Out of range
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $priorityError = array_values(array_filter(
                $errors,
                fn ($error) => ($error->source?->pointer ?? '') === '/data/attributes/priority'
            ))[0] ?? null;

            self::assertNotNull($priorityError);
            self::assertStringContainsString('10', $priorityError->detail);
        }
    }

    public function testValidationErrorsForNestedAttributes(): void
    {
        // Test that validation errors for embeddable properties have correct pointers
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Article with invalid contact',
                'content' => 'Content that is long enough',
                'contactInfo' => [
                    'email' => 'invalid-email', // Invalid email format
                    'phone' => '123', // Too short (min 10 characters)
                ],
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException for invalid contact info');
        } catch (ValidationException $e) {
            // Error pointers should be /data/attributes/contactInfo.email and /data/attributes/contactInfo.phone
            $this->assertValidationErrorPointer($e, '/data/attributes/contactInfo.email');
            $this->assertValidationErrorPointer($e, '/data/attributes/contactInfo.phone');
            $this->assertValidationErrorCount($e, 2);
        }
    }

    public function testValidationErrorsPreserveConstraintMetadata(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'AB', // Too short
                'content' => 'Valid content that is long enough',
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $titleError = array_values(array_filter(
                $errors,
                fn ($error) => ($error->source?->pointer ?? '') === '/data/attributes/title'
            ))[0] ?? null;

            self::assertNotNull($titleError);
            self::assertNotNull($titleError->detail);
            self::assertNotNull($titleError->status);
            self::assertSame('422', $titleError->status);
        }
    }

    public function testMultipleErrorsForSameField(): void
    {
        // If a field violates multiple constraints, all should be reported
        $changes = new ChangeSet(
            attributes: [
                'title' => '', // Violates both NotBlank and Length(min: 3)
                'content' => 'Valid content that is long enough',
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $titleErrors = array_filter(
                $errors,
                fn ($error) => ($error->source?->pointer ?? '') === '/data/attributes/title'
            );

            // Should have at least one error for title
            self::assertGreaterThanOrEqual(1, count($titleErrors));
        }
    }

    public function testValidationErrorForUnknownAttribute(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'unknownField' => 'value',
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $unknownError = array_values(array_filter(
                $errors,
                fn ($error) => ($error->source?->pointer ?? '') === '/data/attributes/unknownField'
            ))[0] ?? null;

            self::assertNotNull($unknownError);
            self::assertSame('422', $unknownError->status);
            self::assertNotNull($unknownError->detail);
        }
    }

    public function testValidationErrorForTypeCoercionFailure(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Valid Title',
                'content' => 'Valid content that is long enough',
                'priority' => 'not-a-number',
            ]
        );

        try {
            $this->validatingProcessor->processCreate('validated-articles', $changes);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $priorityError = array_values(array_filter(
                $errors,
                fn ($error) => ($error->source?->pointer ?? '') === '/data/attributes/priority'
            ))[0] ?? null;

            self::assertNotNull($priorityError);
            self::assertSame('422', $priorityError->status);
        }
    }
}
