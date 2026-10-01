<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Write;

/** @internal */
final class JsonDocument
{
    public static function decode(string $json): mixed
    {
        return self::normalize(json_decode($json, false, 512, \JSON_THROW_ON_ERROR));
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $members = get_object_vars($value);
            return $members === [] ? $value : array_map(self::normalize(...), $members);
        }
        return is_array($value) ? array_map(self::normalize(...), $value) : $value;
    }
}
