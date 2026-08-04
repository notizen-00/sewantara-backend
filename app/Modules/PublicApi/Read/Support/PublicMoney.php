<?php

namespace App\Modules\PublicApi\Read\Support;

use InvalidArgumentException;

final class PublicMoney
{
    /** @var array<string, int> */
    private const EXPONENTS = [
        'BIF' => 0,
        'CLP' => 0,
        'DJF' => 0,
        'GNF' => 0,
        'IDR' => 0,
        'ISK' => 0,
        'JPY' => 0,
        'KMF' => 0,
        'KRW' => 0,
        'PYG' => 0,
        'RWF' => 0,
        'UGX' => 0,
        'VND' => 0,
        'VUV' => 0,
        'XAF' => 0,
        'XOF' => 0,
        'XPF' => 0,
        'BHD' => 3,
        'IQD' => 3,
        'JOD' => 3,
        'KWD' => 3,
        'LYD' => 3,
        'OMR' => 3,
        'TND' => 3,
    ];

    /**
     * @return array{amount: int, currency: string}
     */
    public function payload(mixed $amount, string $currency): array
    {
        $currency = strtoupper($currency);

        return [
            'amount' => $this->minorAmount($amount, $currency),
            'currency' => $currency,
        ];
    }

    public function minorAmount(mixed $amount, string $currency): int
    {
        $value = trim((string) $amount);

        if (preg_match('/^(?<sign>-?)(?<whole>\d+)(?:\.(?<fraction>\d+))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Nilai uang tidak valid.');
        }

        $exponent = $this->exponent($currency);
        $fraction = (string) ($matches['fraction'] ?? '');
        $fraction = str_pad($fraction, $exponent + 1, '0');
        $keptFraction = $exponent === 0
            ? ''
            : substr($fraction, 0, $exponent);
        $multiplier = 10 ** $exponent;
        $minor = ((int) $matches['whole'] * $multiplier)
            + ($keptFraction === '' ? 0 : (int) $keptFraction);

        if ((int) ($fraction[$exponent] ?? '0') >= 5) {
            $minor++;
        }

        return ($matches['sign'] ?? '') === '-' ? -$minor : $minor;
    }

    public function majorAmount(int $minorAmount, string $currency): string
    {
        $exponent = $this->exponent($currency);

        if ($exponent === 0) {
            return (string) $minorAmount;
        }

        $negative = $minorAmount < 0;
        $absolute = abs($minorAmount);
        $multiplier = 10 ** $exponent;
        $whole = intdiv($absolute, $multiplier);
        $fraction = str_pad(
            (string) ($absolute % $multiplier),
            $exponent,
            '0',
            STR_PAD_LEFT,
        );

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function exponent(string $currency): int
    {
        return self::EXPONENTS[strtoupper($currency)] ?? 2;
    }
}
