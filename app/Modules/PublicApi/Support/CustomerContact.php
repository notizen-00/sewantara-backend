<?php

namespace App\Modules\PublicApi\Support;

final class CustomerContact
{
    public static function phone(string $value): string
    {
        $phone = preg_replace('/[^0-9+]/', '', trim($value)) ?? '';

        if (str_starts_with($phone, '0')) {
            return '+62'.substr($phone, 1);
        }

        if (str_starts_with($phone, '62')) {
            return '+'.$phone;
        }

        return $phone;
    }

    public static function email(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    public static function maskPhone(string $value): string
    {
        $length = strlen($value);

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 3)
            .str_repeat('*', max(3, $length - 6))
            .substr($value, -3);
    }

    public static function maskEmail(?string $value): ?string
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $value, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
