<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Validation;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\ValidationException;
use JsonApi\Symfony\Http\Validation\DatabaseErrorMapper;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;

final class DatabaseErrorMapperTest extends TestCase
{
    private ResourceRegistryInterface $registry;
    private ErrorMapper $errorMapper;
    private DatabaseErrorMapper $mapper;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ResourceRegistryInterface::class);
        $this->errorMapper = new ErrorMapper(new ErrorBuilder(false));
        $this->mapper = new DatabaseErrorMapper($this->registry, $this->errorMapper);
    }

    public function testMapUniqueConstraintViolation(): void
    {
        $metadata = new ResourceMetadata(
            type: 'users',
            class: 'User',
            attributes: [
                'email' => new AttributeMetadata('email', 'string'),
            ],
            relationships: []
        );

        $this->registry->expects($this->once())
            ->method('getByType')
            ->with('users')
            ->willReturn($metadata);

        $driverException = $this->createMock(\Doctrine\DBAL\Driver\Exception::class);
        $exception = new UniqueConstraintViolationException(
            $driverException,
            null
        );

        $result = $this->mapper->mapDatabaseError('users', $exception);

        $this->assertInstanceOf(ConflictException::class, $result);
        $this->assertStringContainsString('constraint violation', $result->getMessage());
    }

    public function testMapForeignKeyConstraintViolation(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'Article',
            attributes: [],
            relationships: [
                'author' => new RelationshipMetadata('author', false, 'User'),
            ]
        );

        $this->registry->expects($this->once())
            ->method('getByType')
            ->with('articles')
            ->willReturn($metadata);

        $driverException = $this->createMock(\Doctrine\DBAL\Driver\Exception::class);
        $exception = new ForeignKeyConstraintViolationException(
            $driverException,
            null
        );

        $result = $this->mapper->mapDatabaseError('articles', $exception);

        $this->assertInstanceOf(ValidationException::class, $result);
    }

    public function testMapOptimisticLockException(): void
    {
        $entity = new \stdClass();
        $exception = new OptimisticLockException('Optimistic lock failed', $entity);

        $result = $this->mapper->mapDatabaseError('users', $exception);

        $this->assertInstanceOf(ConflictException::class, $result);
        $this->assertStringContainsString('lock conflict', $result->getMessage());
    }

    public function testMapUnknownException(): void
    {
        $exception = new \RuntimeException('Unknown database error');

        $result = $this->mapper->mapDatabaseError('users', $exception);

        $this->assertSame($exception, $result);
    }
}
