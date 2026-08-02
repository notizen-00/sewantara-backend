<?php

namespace App\Modules\SubscriptionBilling\Application;

use App\Models\SubscriptionPayment;
use App\Modules\SubscriptionBilling\Events\SubscriptionPaymentPaid;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use UnexpectedValueException;

class ConfirmSubscriptionPayment
{
    public function __construct(
        private readonly ActivateSubscriptionForPaidPayment $activateSubscription,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        string $paymentNumber,
        string $gateway,
        ?string $gatewayReference,
        ?string $gatewaySessionReference,
        array $metadata,
    ): SubscriptionPayment {
        $connection = (new SubscriptionPayment)->getConnection();
        $transitionedToPaid = false;
        $payment = $connection->transaction(function () use (
            $paymentNumber,
            $gateway,
            $gatewayReference,
            $gatewaySessionReference,
            $metadata,
            &$transitionedToPaid,
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

            if (is_string($notifiedCurrency)
                && strtoupper($notifiedCurrency) !== strtoupper($payment->currency)) {
                throw new UnexpectedValueException(
                    'Mata uang notifikasi tidak sesuai dengan tagihan.',
                );
            }

            $wasPaid = $payment->status === 'paid';

            if (! $wasPaid) {
                $payment->forceFill([
                    'status' => 'paid',
                    'gateway_reference' => $gatewayReference,
                    'paid_at' => now(),
                    'metadata' => array_merge(
                        $payment->metadata ?? [],
                        ['payment_notification' => $metadata],
                    ),
                ])->save();

                $transitionedToPaid = true;
            }

            $activationStatus = $payment->metadata['subscription_activation']['status'] ?? null;

            if (! is_string($activationStatus)) {
                $subscription = $this->activateSubscription->execute(
                    $payment,
                    extendActive: ! $wasPaid,
                );
                $payment->forceFill([
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'subscription_activation' => $subscription === null
                            ? [
                                'status' => 'skipped_already_active',
                                'processed_at' => now()->toIso8601String(),
                            ]
                            : [
                                'status' => 'applied',
                                'processed_at' => now()->toIso8601String(),
                                'subscription_id' => $subscription->getKey(),
                                'starts_at' => $subscription->starts_at?->toIso8601String(),
                                'ends_at' => $subscription->ends_at?->toIso8601String(),
                            ],
                    ]),
                ])->save();

                if ($subscription !== null) {
                    $transitionedToPaid = true;
                }
            }

            return $payment;
        }, attempts: 3);

        if ($transitionedToPaid) {
            SubscriptionPaymentPaid::dispatch(
                $payment->getKey(),
                $payment->tenant_id,
            );
        }

        return $payment;
    }
}
