<?php

namespace App\Modules\SubscriptionBilling\Application;

use App\Models\SubscriptionPayment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use UnexpectedValueException;

class ExpireSubscriptionPayment
{
    /** @param array<string, mixed> $metadata */
    public function execute(
        string $paymentNumber,
        string $gateway,
        ?string $gatewaySessionReference,
        array $metadata,
    ): SubscriptionPayment {
        $connection = (new SubscriptionPayment)->getConnection();

        return $connection->transaction(function () use (
            $paymentNumber,
            $gateway,
            $gatewaySessionReference,
            $metadata,
        ): SubscriptionPayment {
            $payment = SubscriptionPayment::query()
                ->where('payment_number', $paymentNumber)
                ->where('gateway', $gateway)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw (new ModelNotFoundException)->setModel(SubscriptionPayment::class);
            }

            $storedSessionReference = $payment->metadata['checkout']['token']
                ?? ($payment->status === 'paid' ? null : $payment->gateway_reference);

            if ($gatewaySessionReference !== null
                && is_string($storedSessionReference)
                && ! hash_equals($storedSessionReference, $gatewaySessionReference)) {
                throw new UnexpectedValueException(
                    'Referensi sesi gateway tidak sesuai dengan pembayaran.',
                );
            }

            $notifiedAmount = $metadata['gross_amount'] ?? null;
            $notifiedCurrency = $metadata['currency'] ?? null;

            if (! is_numeric($notifiedAmount)
                || (int) round((float) $payment->amount * 100)
                    !== (int) round((float) $notifiedAmount * 100)) {
                throw new UnexpectedValueException(
                    'Nominal notifikasi tidak sesuai dengan tagihan.',
                );
            }

            if (! is_string($notifiedCurrency)
                || strtoupper($notifiedCurrency) !== strtoupper($payment->currency)) {
                throw new UnexpectedValueException(
                    'Mata uang notifikasi tidak sesuai dengan tagihan.',
                );
            }

            if ($payment->status === 'pending') {
                $payment->forceFill([
                    'status' => 'expired',
                    'metadata' => array_merge(
                        $payment->metadata ?? [],
                        ['payment_expiration_notification' => $metadata],
                    ),
                ])->save();
            }

            return $payment;
        }, attempts: 3);
    }
}
