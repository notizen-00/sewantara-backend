<?php

namespace App\Modules\PublicApi\Support;

use LogicException;

final class TrackingToken
{
    public static function derive(
        string $tenantId,
        string $bookingPublicId,
        string $idempotencyKey,
    ): string {
        $secret = (string) config('app.key');

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            $secret = is_string($decoded) ? $decoded : '';
        }

        if ($secret === '') {
            throw new LogicException('Application key wajib tersedia untuk tracking token.');
        }

        $bytes = hash_hmac(
            'sha256',
            implode('|', ['public-tracking-v1', $tenantId, $bookingPublicId, $idempotencyKey]),
            $secret,
            true,
        );

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function digest(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function matches(?string $storedHash, string $token): bool
    {
        return is_string($storedHash)
            && strlen($storedHash) === 64
            && hash_equals($storedHash, self::digest($token));
    }
}
