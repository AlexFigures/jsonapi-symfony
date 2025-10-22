<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Uid\Uuid;

/**
 * Custom Doctrine type for Symfony Uuid.
 *
 * Converts Uuid objects to/from RFC 4122 string representation for database storage.
 */
final class UuidType extends Type
{
    public const NAME = 'uuid';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    /**
     * Converts a database value to a PHP Uuid object.
     *
     * @param mixed $value The database value (UUID string)
     * @param AbstractPlatform $platform The database platform
     * @return Uuid|null The Uuid object or null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uuid
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Uuid) {
            return $value;
        }

        return Uuid::fromString((string) $value);
    }

    /**
     * Converts a PHP Uuid object to a database value.
     *
     * @param mixed $value The PHP value (Uuid object)
     * @param AbstractPlatform $platform The database platform
     * @return string|null The UUID string or null
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Uuid) {
            return $value->toRfc4122();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}

