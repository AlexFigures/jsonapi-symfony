<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Doctrine\Type;

use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for DateTimeZone.
 *
 * Converts DateTimeZone objects to/from timezone name strings for database storage.
 */
final class DateTimeZoneType extends Type
{
    public const NAME = 'datetimezone';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    /**
     * Converts a database value to a PHP DateTimeZone object.
     *
     * @param  mixed             $value    The database value (timezone name string)
     * @param  AbstractPlatform  $platform The database platform
     * @return DateTimeZone|null The DateTimeZone object or null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeZone
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeZone) {
            return $value;
        }

        return new DateTimeZone((string) $value);
    }

    /**
     * Converts a PHP DateTimeZone object to a database value.
     *
     * @param  mixed            $value    The PHP value (DateTimeZone object)
     * @param  AbstractPlatform $platform The database platform
     * @return string|null      The timezone name string or null
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeZone) {
            return $value->getName();
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
