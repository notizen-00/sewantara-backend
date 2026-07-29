<?php

namespace App\Modules\SubscriptionBilling\Application;

use App\Models\SubscriptionPayment;
use App\Modules\SubscriptionBilling\Events\SubscriptionPaymentPaid;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class ConfirmSubscriptionPayment
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        string $paymentNumber,
        ?string $gatewayReference,
        array $metadata,
    ): SubscriptionPayment {
        $payment = DB::transaction(function () use (
            $paymentNumber,
            $gatewayReference,
            $metadata,
        ): SubscriptionPayment {
            $payment = SubscriptionPayment::query()
                ->where('payment_number', $paymentNumber)
                ->lockForUpdate()
                ->firstOrFail();

            $notifiedAmount = $metadata['gross_amount'] ?? null;

            if (! is_numeric($notifiedAmount)
                || (int) round((float) $payment->amount * 100)
                    !== (int) round((float) $notifiedAmount * 100)) {
                throw new UnexpectedValueException(
                    'Nominal notifikasi tidak sesuai dengan tagihan.',
                );
            }

            if ($payment->status !== 'paid') {
                $payment->forceFill([
                    'status' => 'paid',
                    'gateway_reference' => $gatewayReference,
                    'paid_at' => now(),
                    'metadata' => array_merge(
                        $payment->metadata ?? [],
                        ['payment_notification' => $metadata],
                    ),
                ])->save();
            }

            return $payment;
        }, attempts: 3);

        SubscriptionPaymentPaid::dispatch(
            $payment->getKey(),
            $payment->tenant_id,
        );

        return $payment;
    }
}
