<?php

namespace App\Modules\PublicApi\Support;

use UnexpectedValueException;

final class PublicMoney
{
    public static function fromDatabase(mixed $amount, string $currency = 'IDR'): int
    {
        $value = trim((string) $amount);

        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new UnexpectedValueException('Nominal uang internal tidak valid.');
        }

        $whole = (int) $matches[1];
        $fraction = $matches[2] ?? '';

        if (strtoupper($currency) === 'IDR') {
            if (trim($fraction, '0') !== '') {
                throw new UnexpectedValueException('Nominal IDR harus berupa rupiah utuh.');
            }

            return $whole;
        }

        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            throw new UnexpectedValueException('Nominal memiliki pecahan yang tidak didukung.');
        }

        return ($whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
