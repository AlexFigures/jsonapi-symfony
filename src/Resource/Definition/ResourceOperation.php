<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Resource\Definition;

/**
 * Defines the available JSON:API resource operations.
 *
 * Each operation maps to specific HTTP methods and route patterns.
 * Resources can selectively enable/disable operations via the JsonApiResource attribute.
 *
 * @api This enum is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
enum ResourceOperation: string
{
    /**
     * Collection GET/HEAD operation.
     * Retrieves a list of resources.
     */
    case INDEX = 'index';

    /**
     * Item GET/HEAD operation.
     * Retrieves a single resource by ID.
     */
    case SHOW = 'show';

    /**
     * POST operation.
     * Creates a new resource.
     */
    case CREATE = 'create';

    /**
     * PATCH operation.
     * Updates an existing resource.
     */
    case UPDATE = 'update';

    /**
     * DELETE operation.
     * Deletes an existing resource.
     */
    case DELETE = 'delete';

    /**
     * Get HTTP methods for this operation.
     *
     * @return list<string>
     */
    public function httpMethods(): array
    {
        return match ($this) {
            self::INDEX, self::SHOW => ['GET', 'HEAD'],
            self::CREATE => ['POST'],
            self::UPDATE => ['PATCH'],
            self::DELETE => ['DELETE'],
        };
    }

    /**
     * Get route key suffix for this operation.
     *
     * Used to construct route names like 'jsonapi.articles.index' or 'jsonapi.articles.show'.
     */
    public function routeKey(): string
    {
        return $this->value;
    }

    /**
     * Check if this is a collection operation.
     */
    public function isCollection(): bool
    {
        return $this === self::INDEX;
    }

    /**
     * Check if this is an item operation.
     */
    public function isItem(): bool
    {
        return match ($this) {
            self::SHOW, self::UPDATE, self::DELETE => true,
            default => false,
        };
    }

    /**
     * Check if this is a write operation.
     */
    public function isWrite(): bool
    {
        return match ($this) {
            self::CREATE, self::UPDATE, self::DELETE => true,
            default => false,
        };
    }

    /**
     * Check if this is a read operation.
     */
    public function isRead(): bool
    {
        return match ($this) {
            self::INDEX, self::SHOW => true,
            default => false,
        };
    }
}
