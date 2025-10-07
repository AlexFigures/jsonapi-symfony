<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Atomic;

use JsonApi\Symfony\Atomic\AtomicConfig;
use JsonApi\Symfony\Atomic\Operation;
use JsonApi\Symfony\Atomic\Ref;
use JsonApi\Symfony\Atomic\Validation\AtomicValidator;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests edge cases for AtomicValidator to kill escaped mutants.
 * 
 * Targets escaped mutants in src/Atomic/Validation/AtomicValidator.php:
 * - Logical operators (||, &&, !)
 * - String validation (empty strings)
 * - Array validation
 */
#[CoversClass(AtomicValidator::class)]
final class AtomicValidatorEdgeCasesTest extends TestCase
{
    private AtomicValidator $validator;

    protected function setUp(): void
    {
        $config = new AtomicConfig(
            enabled: true,
            allowHref: true,
        );
        $registry = new ResourceRegistry([Article::class, Author::class]);
        $errorBuilder = new ErrorBuilder(useDefaultTitleMap: true);
        $errors = new ErrorMapper($errorBuilder);

        $this->validator = new AtomicValidator($config, $registry, $errors);
    }

    /**
     * Test that operation with both ref and href throws error.
     * Kills mutant: AtomicValidator.php:53 (LogicalAnd - $operation->ref !== null && $operation->href !== null)
     */
    public function testOperationWithBothRefAndHrefThrowsError(): void
    {
        $operation = new Operation(
            op: 'add',
            ref: new Ref(type: 'articles', id: null, lid: null, relationship: null),
            href: '/api/articles',
            data: ['type' => 'articles', 'attributes' => []],
            meta: [],
            pointer: '/atomic:operations/0'
        );

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid target specification');

        $this->validator->validate([$operation]);
    }

    /**
     * Test that operation without ref or href throws error.
     * Kills mutant: AtomicValidator.php:60-61 (Identical - $ref === null check)
     */
    public function testOperationWithoutRefOrHrefThrowsError(): void
    {
        $operation = new Operation(
            op: 'add',
            ref: null,
            href: null,
            data: ['type' => 'articles', 'attributes' => []],
            meta: [],
            pointer: '/atomic:operations/0'
        );

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Missing operation target');
        
        $this->validator->validate([$operation]);
    }

    /**
     * Test that href is rejected when allowHref is false.
     * Kills mutant: AtomicValidator.php:67 (LogicalNot - !$this->config->allowHref)
     */
    public function testHrefRejectedWhenDisabled(): void
    {
        $config = new AtomicConfig(
            enabled: true,
            allowHref: false,  // Disable href
        );
        $registry = new ResourceRegistry([Article::class]);
        $errorBuilder = new ErrorBuilder(useDefaultTitleMap: true);
        $errors = new ErrorMapper($errorBuilder);
        $validator = new AtomicValidator($config, $registry, $errors);

        $operation = new Operation(
            op: 'add',
            ref: null,
            href: '/api/articles',
            data: ['type' => 'articles', 'attributes' => []],
            meta: [],
            pointer: '/atomic:operations/0'
        );

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Href targets are disabled');

        $validator->validate([$operation]);
    }

    /**
     * Test that unknown resource type throws error.
     * Kills mutant: AtomicValidator.php:78 (LogicalNot - !$this->registry->hasType)
     */
    public function testUnknownResourceTypeThrowsError(): void
    {
        $operation = new Operation(
            op: 'add',
            ref: new Ref(type: 'unknown-type', id: null, lid: null, relationship: null),
            href: null,
            data: ['type' => 'unknown-type', 'attributes' => []],
            meta: [],
            pointer: '/atomic:operations/0'
        );

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unknown resource type');
        
        $this->validator->validate([$operation]);
    }

    /**
     * Test that unknown relationship throws error.
     * Kills mutant: AtomicValidator.php:90-91 (Identical - $relationship === null check)
     */
    public function testUnknownRelationshipThrowsError(): void
    {
        $operation = new Operation(
            op: 'update',
            ref: new Ref(type: 'articles', id: 'some-id', lid: null, relationship: 'unknown-rel'),
            href: null,
            data: ['type' => 'authors', 'id' => 'author-id'],
            meta: [],
            pointer: '/atomic:operations/0'
        );

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unknown relationship');
        
        $this->validator->validate([$operation]);
    }

    /**
     * Test that add operation requires data.
     * Kills mutants related to data validation
     */
    public function testAddOperationWithoutDataThrowsError(): void
    {
        $operation = new Operation(
            op: 'add',
            ref: new Ref(type: 'articles', id: null, lid: null, relationship: null),
            href: null,
            data: null,  // Missing data
            meta: [],
            pointer: '/atomic:operations/0'
        );

        $this->expectException(BadRequestException::class);
        
        $this->validator->validate([$operation]);
    }

    /**
     * Test that resource data must have a type.
     * Kills mutant: AtomicValidator.php:114 (LogicalOr - !is_string || $dataType === '')
     */
    public function testResourceDataWithoutTypeThrowsError(): void
    {
        $operation = new Operation(
            op: 'add',
            ref: new Ref(type: 'articles', id: null, lid: null, relationship: null),
            href: null,
            data: ['attributes' => []],  // Missing type
            meta: [],
            pointer: '/atomic:operations/0'
        );

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('type');
        
        $this->validator->validate([$operation]);
    }

    /**
     * Test that resource data type cannot be empty string.
     * Kills mutant: AtomicValidator.php:114 (NotIdentical - $dataType !== '')
     */
    public function testResourceDataWithEmptyStringTypeThrowsError(): void
    {
        $operation = new Operation(
            op: 'add',
            ref: new Ref(type: 'articles', id: null, lid: null, relationship: null),
            href: null,
            data: ['type' => '', 'attributes' => []],  // Empty string type
            meta: [],
            pointer: '/atomic:operations/0'
        );

        $this->expectException(BadRequestException::class);
        
        $this->validator->validate([$operation]);
    }

    /**
     * Test that resource data type must match ref type.
     * Kills mutant: AtomicValidator.php:120 (NotIdentical - $dataType !== $ref->type)
     */
    public function testResourceDataTypeMismatchThrowsError(): void
    {
        $operation = new Operation(
            op: 'add',
            ref: new Ref(type: 'articles', id: null, lid: null, relationship: null),
            href: null,
            data: ['type' => 'authors', 'attributes' => []],  // Type mismatch
            meta: [],
            pointer: '/atomic:operations/0'
        );

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Type mismatch');
        
        $this->validator->validate([$operation]);
    }

    /**
     * Test that unsupported operation code throws error.
     * Kills mutant: AtomicValidator.php:47 (in_array check)
     */
    public function testUnsupportedOperationCodeThrowsError(): void
    {
        $operation = new Operation(
            op: 'merge',  // Unsupported operation
            ref: new Ref(type: 'articles', id: null, lid: null, relationship: null),
            href: null,
            data: ['type' => 'articles', 'attributes' => []],
            meta: [],
            pointer: '/atomic:operations/0'
        );

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unsupported');
        
        $this->validator->validate([$operation]);
    }

    /**
     * Test that valid add operation passes validation.
     * Ensures the happy path works correctly.
     */
    public function testValidAddOperationPassesValidation(): void
    {
        $operation = new Operation(
            op: 'add',
            ref: new Ref(type: 'articles', id: null, lid: null, relationship: null),
            href: null,
            data: ['type' => 'articles', 'attributes' => ['title' => 'Test']],
            meta: [],
            pointer: '/atomic:operations/0'
        );

        [$validated, $lids] = $this->validator->validate([$operation]);
        
        self::assertCount(1, $validated);
        self::assertSame('add', $validated[0]->op);
    }
}

