<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Http\Validation;

use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Tests that ConstraintViolationMapper correctly handles multiple violations
 * on the same field without deduplication.
 *
 * Regression test for: "short-circuits any second violation that resolves to
 * an already-seen pointer. That means if a field violates two different
 * constraints (e.g. NotBlank and UniqueEntity, or length + regexp) only the
 * first error survives."
 */
final class MultipleViolationsTest extends TestCase
{
    private ConstraintViolationMapper $mapper;
    private ResourceRegistryInterface $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ResourceRegistryInterface::class);
        $errorBuilder = new ErrorBuilder(false);
        $errorMapper = new ErrorMapper($errorBuilder);
        $this->mapper = new ConstraintViolationMapper($this->registry, $errorMapper);
    }

    /**
     * Test: Multiple violations on the same field should all be preserved.
     *
     * A field can violate multiple constraints (e.g., NotBlank + Length,
     * or UniqueEntity + Format). All violations must be returned to give
     * consumers actionable feedback.
     */
    public function testMultipleViolationsOnSameFieldArePreserved(): void
    {
        $metadata = new ResourceMetadata(
            type: 'users',
            class: \stdClass::class,
            attributes: [
                'email' => new AttributeMetadata('email', 'string'),
            ],
            relationships: []
        );

        $this->registry->expects($this->once())
            ->method('getByType')
            ->with('users')
            ->willReturn($metadata);

        // Create multiple violations for the same field
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'This value should not be blank.',
                null,
                [],
                null,
                'email',
                ''
            ),
            new ConstraintViolation(
                'This value is not a valid email address.',
                null,
                [],
                null,
                'email',
                'invalid-email'
            ),
            new ConstraintViolation(
                'This value is too short. It should have 5 characters or more.',
                null,
                [],
                null,
                'email',
                'abc'
            ),
        ]);

        $errors = $this->mapper->map('users', $violations);

        // All three violations should be preserved
        $this->assertCount(3, $errors, 'All violations on the same field must be preserved');

        // All should point to the same field
        foreach ($errors as $error) {
            $this->assertSame('/data/attributes/email', $error->source?->pointer);
        }

        // Each should have a different message
        $messages = array_map(fn ($error) => $error->detail, $errors);
        $this->assertCount(3, array_unique($messages), 'Each violation should have a unique message');

        // Check that all three distinct messages are present
        $messagesString = implode(' | ', $messages);
        $this->assertStringContainsString('should not be blank', $messagesString);
        $this->assertStringContainsString('not a valid email address', $messagesString);
        $this->assertStringContainsString('too short', $messagesString);
    }

    /**
     * Test: Multiple violations on different fields should all be preserved.
     */
    public function testMultipleViolationsOnDifferentFieldsArePreserved(): void
    {
        $metadata = new ResourceMetadata(
            type: 'users',
            class: \stdClass::class,
            attributes: [
                'email' => new AttributeMetadata('email', 'string'),
                'username' => new AttributeMetadata('username', 'string'),
            ],
            relationships: []
        );

        $this->registry->expects($this->once())
            ->method('getByType')
            ->with('users')
            ->willReturn($metadata);

        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'Email is required.',
                null,
                [],
                null,
                'email',
                ''
            ),
            new ConstraintViolation(
                'Username is required.',
                null,
                [],
                null,
                'username',
                ''
            ),
        ]);

        $errors = $this->mapper->map('users', $violations);

        $this->assertCount(2, $errors);

        $pointers = array_map(fn ($error) => $error->source?->pointer, $errors);
        $this->assertContains('/data/attributes/email', $pointers);
        $this->assertContains('/data/attributes/username', $pointers);
    }
}
