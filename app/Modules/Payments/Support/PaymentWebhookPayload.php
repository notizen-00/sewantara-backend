<?php

namespace App\Modules\Payments\Support;

class PaymentWebhookPayload
{
    /** @param array<string, mixed> $payload */
    public static function canonicalHash(array $payload): string
    {
        return hash('sha256', json_encode(
            self::canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * Keep the audit copy useful without persisting signatures, credentials,
     * customer details, card data, or provider-specific opaque objects.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, bool|int|float|string|null>
     */
    public static function forAudit(array $payload): array
    {
        $allowed = [
            'order_id',
            'transaction_id',
            'transaction_status',
            'fraud_status',
            'status_code',
            'gross_amount',
            'currency',
            'payment_type',
            'transaction_time',
            'settlement_time',
        ];
        $redacted = [];

        foreach ($allowed as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value)) {
                $redacted[$key] = mb_substr($value, 0, 500);
            } elseif (is_int($value) || is_float($value) || is_bool($value)) {
                $redacted[$key] = $value;
            } elseif ($value === null && array_key_exists($key, $payload)) {
                $redacted[$key] = null;
            }
        }

        return $redacted;
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }
}
