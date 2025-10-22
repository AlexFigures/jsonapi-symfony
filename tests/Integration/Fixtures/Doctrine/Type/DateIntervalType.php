<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Doctrine\Type;

use DateInterval;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for DateInterval.
 *
 * Converts DateInterval objects to/from ISO 8601 duration strings for database storage.
 */
final class DateIntervalType extends Type
{
    public const NAME = 'dateinterval';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    /**
     * Converts a database value to a PHP DateInterval object.
     *
     * @param  mixed             $value    The database value (ISO 8601 duration string)
     * @param  AbstractPlatform  $platform The database platform
     * @return DateInterval|null The DateInterval object or null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateInterval
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateInterval) {
            return $value;
        }

        return new DateInterval((string) $value);
    }

    /**
     * Converts a PHP DateInterval object to a database value.
     *
     * @param  mixed            $value    The PHP value (DateInterval object)
     * @param  AbstractPlatform $platform The database platform
     * @return string|null      The ISO 8601 duration string or null
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateInterval) {
            // Convert DateInterval to ISO 8601 duration format
            return $this->formatDateInterval($value);
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

    /**
     * Formats a DateInterval as an ISO 8601 duration string.
     *
     * @param  DateInterval $interval The DateInterval to format
     * @return string       The ISO 8601 duration string
     */
    private function formatDateInterval(DateInterval $interval): string
    {
        $format = 'P';

        if ($interval->y > 0) {
            $format .= $interval->y . 'Y';
        }
        if ($interval->m > 0) {
            $format .= $interval->m . 'M';
        }
        if ($interval->d > 0) {
            $format .= $interval->d . 'D';
        }

        $hasTime = $interval->h > 0 || $interval->i > 0 || $interval->s > 0;

        if ($hasTime) {
            $format .= 'T';

            if ($interval->h > 0) {
                $format .= $interval->h . 'H';
            }
            if ($interval->i > 0) {
                $format .= $interval->i . 'M';
            }
            if ($interval->s > 0) {
                $format .= $interval->s . 'S';
            }
        }

        // If the interval is zero, return P0D
        if ($format === 'P') {
            return 'P0D';
        }

        return $format;
    }
}
