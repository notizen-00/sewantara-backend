<?php

namespace App\Modules\PublicApi\Support;

use JsonException;

final class CanonicalPayload
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function hash(array $payload): string
    {
        return hash('sha256', self::json($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public static function json(array $payload): string
    {
        return json_encode(
            self::sort($payload),
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES,
        );
    }

    private static function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::sort(...), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(self::sort(...), $value);
    }
}
