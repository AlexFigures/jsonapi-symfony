<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Validation;

use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\ValidationException;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;

/**
 * Maps database exceptions to JSON:API errors.
 * 
 * Handles constraint violations, optimistic locking, and other database-level errors
 * with proper JSON:API error formatting and source pointers.
 */
final class DatabaseErrorMapper
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        private readonly ErrorMapper $errorMapper,
    ) {
    }

    /**
     * Maps database exceptions to appropriate HTTP exceptions.
     * 
     * @throws ConflictException|ValidationException
     */
    public function mapDatabaseError(string $resourceType, \Throwable $exception): \Throwable
    {
        return match (true) {
            $exception instanceof UniqueConstraintViolationException => $this->mapUniqueConstraintViolation($resourceType, $exception),
            $exception instanceof ForeignKeyConstraintViolationException => $this->mapForeignKeyConstraintViolation($resourceType, $exception),
            $exception instanceof OptimisticLockException => $this->mapOptimisticLockException($resourceType, $exception),
            $exception instanceof ConstraintViolationException => $this->mapGenericConstraintViolation($resourceType, $exception),
            default => $exception, // Re-throw unknown exceptions
        };
    }

    /**
     * Maps unique constraint violations to ConflictException.
     */
    private function mapUniqueConstraintViolation(string $resourceType, UniqueConstraintViolationException $exception): ConflictException
    {
        $metadata = $this->registry->getByType($resourceType);
        $constraintName = $this->extractConstraintName($exception);
        $field = $this->mapConstraintToField($metadata, $constraintName);
        
        $pointer = $field ? sprintf('/data/attributes/%s', $field) : '/data';
        $message = $field 
            ? sprintf('The value for "%s" is already in use.', $field)
            : 'A unique constraint violation occurred.';

        $error = $this->errorMapper->conflict($message, $pointer);

        return new ConflictException('Unique constraint violation', [$error]);
    }

    /**
     * Maps foreign key constraint violations to ValidationException.
     */
    private function mapForeignKeyConstraintViolation(string $resourceType, ForeignKeyConstraintViolationException $exception): ValidationException
    {
        $metadata = $this->registry->getByType($resourceType);
        $constraintName = $this->extractConstraintName($exception);
        $relationship = $this->mapConstraintToRelationship($metadata, $constraintName);
        
        $pointer = $relationship 
            ? sprintf('/data/relationships/%s/data', $relationship)
            : '/data/relationships';
        
        $message = $relationship
            ? sprintf('The referenced "%s" does not exist.', $relationship)
            : 'A referenced entity does not exist.';

        $error = $this->errorMapper->validationError(
            $pointer,
            $message,
            [
                'constraint' => $constraintName,
                'relationship' => $relationship,
            ]
        );

        return new ValidationException([$error]);
    }

    /**
     * Maps optimistic lock exceptions to ConflictException.
     */
    private function mapOptimisticLockException(string $resourceType, OptimisticLockException $exception): ConflictException
    {
        $error = $this->errorMapper->conflict(
            'The resource was modified by another request. Please refresh and try again.',
            '/data'
        );

        return new ConflictException('Optimistic lock conflict', [$error]);
    }

    /**
     * Maps generic constraint violations to ValidationException.
     */
    private function mapGenericConstraintViolation(string $resourceType, ConstraintViolationException $exception): ValidationException
    {
        $error = $this->errorMapper->validationError(
            '/data',
            'A database constraint was violated.'
        );

        return new ValidationException([$error]);
    }

    /**
     * Extracts constraint name from exception message.
     */
    private function extractConstraintName(\Throwable $exception): ?string
    {
        $message = $exception->getMessage();
        
        // Try to extract constraint name from common database error patterns
        if (preg_match('/constraint ["`]?([^"`\s]+)["`]?/i', $message, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/key ["`]?([^"`\s]+)["`]?/i', $message, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Maps database constraint name to resource attribute field.
     */
    private function mapConstraintToField(ResourceMetadata $metadata, ?string $constraintName): ?string
    {
        if (!$constraintName) {
            return null;
        }

        // Try to match constraint name to attribute names
        foreach ($metadata->attributes as $attributeName => $attributeMetadata) {
            $propertyPath = $attributeMetadata->propertyPath ?? $attributeName;
            
            // Check if constraint name contains the property path or attribute name
            if (stripos($constraintName, $propertyPath) !== false || 
                stripos($constraintName, $attributeName) !== false) {
                return $attributeName;
            }
        }

        return null;
    }

    /**
     * Maps database constraint name to resource relationship field.
     */
    private function mapConstraintToRelationship(ResourceMetadata $metadata, ?string $constraintName): ?string
    {
        if (!$constraintName) {
            return null;
        }

        // Try to match constraint name to relationship names
        foreach ($metadata->relationships as $relationshipName => $relationshipMetadata) {
            $propertyPath = $relationshipMetadata->propertyPath ?? $relationshipName;
            
            // Check if constraint name contains the property path or relationship name
            if (stripos($constraintName, $propertyPath) !== false || 
                stripos($constraintName, $relationshipName) !== false) {
                return $relationshipName;
            }
        }

        return null;
    }
}
