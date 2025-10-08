<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Integration\PostgreSQL;

use JsonApi\Symfony\Contract\Data\ChangeSet;
use JsonApi\Symfony\Http\Exception\ValidationException;
use JsonApi\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use JsonApi\Symfony\Tests\Integration\Fixtures\Entity\Product;

final class ValidatingPersisterTest extends DoctrineIntegrationTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES']
            ?? 'postgresql://jsonapi:secret@localhost:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    // ==================== Successful validation ====================

    public function testCreateWithValidDataSucceeds(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Laptop',
                'price' => '999.99',
                'stock' => 10,
            ],
        );

        $product = $this->validatingPersister->create('products', $changes);

        self::assertInstanceOf(Product::class, $product);
        self::assertSame('Laptop', $product->getName());
        self::assertSame('999.99', $product->getPrice());
        self::assertSame(10, $product->getStock());
    }

    public function testUpdateWithValidDataSucceeds(): void
    {
        // Create product
        $product = new Product();
        $product->setId('product-1');
        $product->setName('Laptop');
        $product->setPrice('999.99');
        $product->setStock(10);
        $this->em->persist($product);
        $this->em->flush();
        $this->em->clear();

        // Update
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Gaming Laptop',
                'price' => '1499.99',
            ],
        );

        $updated = $this->validatingPersister->update('products', 'product-1', $changes);

        self::assertSame('Gaming Laptop', $updated->getName());
        self::assertSame('1499.99', $updated->getPrice());
        self::assertSame(10, $updated->getStock()); // Unchanged
    }

    // ==================== NotBlank validation ====================

    public function testCreateWithBlankNameFails(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => '',
                'price' => '999.99',
                'stock' => 10,
            ],
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertSame('/data/attributes/name', $errors[0]->source?->pointer);
            self::assertStringContainsString('cannot be blank', $errors[0]->detail ?? '');
            throw $e;
        }
    }

    public function testCreateWithBlankPriceFails(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Laptop',
                'price' => '',
                'stock' => 10,
            ],
        );

        $this->expectException(ValidationException::class);

        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertSame('/data/attributes/price', $errors[0]->source?->pointer);
            throw $e;
        }
    }

    // ==================== Length validation ====================

    public function testCreateWithTooShortNameFails(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'AB', // Minimum 3 characters
                'price' => '999.99',
                'stock' => 10,
            ],
        );

        $this->expectException(ValidationException::class);

        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertSame('/data/attributes/name', $errors[0]->source?->pointer);
            self::assertStringContainsString('at least 3 characters', $errors[0]->detail ?? '');
            throw $e;
        }
    }

    public function testCreateWithTooLongNameFails(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => str_repeat('A', 256), // Maximum 255 characters
                'price' => '999.99',
                'stock' => 10,
            ],
        );

        $this->expectException(ValidationException::class);

        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertSame('/data/attributes/name', $errors[0]->source?->pointer);
            self::assertStringContainsString('cannot be longer than 255', $errors[0]->detail ?? '');
            throw $e;
        }
    }

    // ==================== Positive validation ====================

    public function testCreateWithNegativePriceFails(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Laptop',
                'price' => '-100.00',
                'stock' => 10,
            ],
        );

        $this->expectException(ValidationException::class);

        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertSame('/data/attributes/price', $errors[0]->source?->pointer);
            self::assertStringContainsString('must be positive', $errors[0]->detail ?? '');
            throw $e;
        }
    }

    public function testCreateWithZeroPriceFails(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Laptop',
                'price' => '0.00',
                'stock' => 10,
            ],
        );

        $this->expectException(ValidationException::class);

        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertSame('/data/attributes/price', $errors[0]->source?->pointer);
            throw $e;
        }
    }

    // ==================== LessThan validation ====================

    public function testCreateWithTooHighPriceFails(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Laptop',
                'price' => '1000000.00', // Maximum 999999.99
                'stock' => 10,
            ],
        );

        $this->expectException(ValidationException::class);

        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertSame('/data/attributes/price', $errors[0]->source?->pointer);
            self::assertStringContainsString('cannot exceed', $errors[0]->detail ?? '');
            throw $e;
        }
    }

    // ==================== Email validation ====================

    public function testCreateWithInvalidEmailFails(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Laptop',
                'price' => '999.99',
                'stock' => 10,
                'contactEmail' => 'not-an-email',
            ],
        );

        $this->expectException(ValidationException::class);

        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertSame('/data/attributes/contactEmail', $errors[0]->source?->pointer);
            self::assertStringContainsString('not a valid email', $errors[0]->detail ?? '');
            throw $e;
        }
    }

    public function testCreateWithValidEmailSucceeds(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Laptop',
                'price' => '999.99',
                'stock' => 10,
                'contactEmail' => 'support@example.com',
            ],
        );

        $product = $this->validatingPersister->create('products', $changes);

        self::assertSame('support@example.com', $product->getContactEmail());
    }

    // ==================== PositiveOrZero validation ====================

    public function testCreateWithNegativeStockFails(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Laptop',
                'price' => '999.99',
                'stock' => -5,
            ],
        );

        $this->expectException(ValidationException::class);

        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertCount(1, $errors);
            self::assertSame('/data/attributes/stock', $errors[0]->source?->pointer);
            self::assertStringContainsString('cannot be negative', $errors[0]->detail ?? '');
            throw $e;
        }
    }

    public function testCreateWithZeroStockSucceeds(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'Laptop',
                'price' => '999.99',
                'stock' => 0,
            ],
        );

        $product = $this->validatingPersister->create('products', $changes);

        self::assertSame(0, $product->getStock());
    }

    // ==================== Multiple errors ====================

    public function testCreateWithMultipleErrorsReturnsAllErrors(): void
    {
        $changes = new ChangeSet(
            attributes: [
                'name' => 'AB', // Too short
                'price' => '-100.00', // Negative value
                'stock' => -5, // Negative value
                'contactEmail' => 'not-an-email', // Invalid email
            ],
        );

        $this->expectException(ValidationException::class);

        try {
            $this->validatingPersister->create('products', $changes);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertGreaterThanOrEqual(4, count($errors)); // At least 4 errors
            throw $e;
        }
    }
}

