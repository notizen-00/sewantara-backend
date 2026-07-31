<?php

namespace App\Modules\Payments\Application;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Modules\Payments\Data\GatewayNotification;
use App\Modules\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class HandlePaymentGatewayNotification
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
    ) {}

    /** @param array<string, mixed> $payload */
    public function execute(string $gatewayName, array $payload): Payment
    {
        $gateway = $this->gateways->driver($gatewayName);
        $notification = $gateway->parseNotification($payload);

        return DB::transaction(function () use (
            $gateway,
            $notification,
        ): Payment {
            $payment = Payment::query()
                ->where('payment_number', $notification->orderId)
                ->where('gateway', $gateway->code())
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->minorUnits((string) $payment->amount)
                !== $this->minorUnits($notification->amount)) {
                throw new UnexpectedValueException(
                    'Nominal notifikasi tidak sesuai dengan pembayaran.',
                );
            }

            PaymentTransaction::query()->updateOrCreate(
                [
                    'payment_id' => $payment->getKey(),
                    'gateway' => $gateway->code(),
                    'transaction_id' => $notification->transactionId,
                ],
                [
                    'tenant_id' => $payment->tenant_id,
                    'callback_payload' => $notification->payload,
                    'signature_valid' => true,
                ],
            );

            if ($notification->status === 'paid' && $payment->status !== 'paid') {
                $this->markPaid($payment, $notification);
            } elseif ($payment->status !== 'paid'
                && in_array($notification->status, ['failed', 'expired'], true)) {
                $payment->update(['status' => $notification->status]);
            }

            return $payment->fresh();
        }, attempts: 3);
    }

    private function markPaid(Payment $payment, GatewayNotification $notification): void
    {
        $payment->update([
            'status' => 'paid',
            'gateway_reference' => $notification->transactionId,
            'paid_at' => now(),
        ]);

        if ($payment->type === 'deposit') {
            return;
        }

        $booking = Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
        $paidAmount = (float) $booking->paid_amount + (float) $payment->amount;
        $remainingAmount = max(0, (float) $booking->total_amount - $paidAmount);

        $booking->update([
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payment_status' => $remainingAmount <= 0 ? 'paid' : 'partial',
        ]);
    }

    private function minorUnits(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new UnexpectedValueException('Format nominal pembayaran tidak valid.');
        }

        return ((int) $matches[1] * 100)
            + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
