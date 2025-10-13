<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Http\Validation;

use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\ConflictException;
use AlexFigures\Symfony\Http\Exception\ValidationException;
use AlexFigures\Symfony\Http\Validation\DatabaseErrorMapper;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
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
