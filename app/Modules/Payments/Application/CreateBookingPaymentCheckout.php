<?php

namespace App\Modules\Payments\Application;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\TenantPaymentMethod;
use App\Modules\Payments\Data\BookingPaymentCheckout;
use App\Modules\Payments\Data\CheckoutRequest;
use App\Modules\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateBookingPaymentCheckout
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly ?RegisterPaymentWebhookRoute $webhookRoutes = null,
    ) {}

    public function execute(
        string $tenantId,
        Booking $booking,
        string $type,
        int $amount,
        string $gatewayName,
        string $notificationUrl,
        ?int $createdBy,
    ): BookingPaymentCheckout {
        $this->validateGateway($gatewayName);
        $gateway = $this->gateways->driver($gatewayName);

        [$payment, $transaction] = DB::transaction(function () use (
            $tenantId,
            $booking,
            $type,
            $amount,
            $gateway,
            $createdBy,
        ): array {
            $lockedBooking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->getKey());
            $this->validateAmount($lockedBooking, $type, $amount);

            $payment = Payment::query()->create([
                'tenant_id' => $tenantId,
                'booking_id' => $booking->getKey(),
                'payment_number' => 'PAY-'.Str::ulid(),
                'type' => $type,
                'method' => 'payment_gateway',
                'amount' => $amount,
                'status' => 'pending',
                'gateway' => $gateway->code(),
                'created_by' => $createdBy,
            ]);

            $transaction = PaymentTransaction::query()->create([
                'tenant_id' => $tenantId,
                'payment_id' => $payment->getKey(),
                'gateway' => $gateway->code(),
                'request_payload' => [
                    'order_id' => $payment->payment_number,
                    'gross_amount' => $amount,
                    'currency' => 'IDR',
                ],
            ]);

            return [$payment, $transaction];
        });

        $webhookRoutes = $this->webhookRoutes
            ?? app(RegisterPaymentWebhookRoute::class);
        $webhookRoutes->register(
            $tenantId,
            $payment,
            $gateway->code(),
        );

        $booking->loadMissing('customer');
        $customer = $booking->customer;
        try {
            $checkout = $gateway->createCheckout(new CheckoutRequest(
                orderId: $payment->payment_number,
                grossAmount: $amount,
                currency: 'IDR',
                customer: array_filter([
                    'first_name' => $customer?->name ?? 'Pelanggan',
                    'email' => $customer?->email,
                    'phone' => $customer?->phone,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
                items: [[
                    'id' => 'booking-'.$booking->getKey(),
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => 'Pembayaran '.$booking->booking_number,
                ]],
                notificationUrl: $notificationUrl,
            ));
        } catch (Throwable $exception) {
            $webhookRoutes->markFailed(
                $gateway->code(),
                $payment->payment_number,
            );
            $payment->update(['status' => 'failed']);
            $transaction->update([
                'response_payload' => [
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                ],
            ]);

            throw $exception;
        }

        $transaction->update([
            'response_payload' => [
                'token' => $checkout->token,
                'redirect_url' => $checkout->redirectUrl,
            ],
        ]);

        return new BookingPaymentCheckout($payment->fresh(), $checkout);
    }

    private function validateGateway(string $gateway): void
    {
        if (! TenantPaymentMethod::query()
            ->where('method', $gateway)
            ->where('is_enabled', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'gateway' => ['Payment gateway belum diaktifkan untuk akun usaha ini.'],
            ]);
        }
    }

    private function validateAmount(Booking $booking, string $type, int $amount): void
    {
        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['Nominal pembayaran minimal Rp1.'],
            ]);
        }

        $pendingAmount = Payment::query()
            ->where('booking_id', $booking->getKey())
            ->where('status', 'pending')
            ->where('type', '!=', 'deposit')
            ->sum('amount');
        $availableAmount = max(
            0,
            (int) $booking->remaining_amount - (int) $pendingAmount,
        );

        if ($type !== 'deposit' && $amount > $availableAmount) {
            throw ValidationException::withMessages([
                'amount' => ['Nominal pembayaran melebihi sisa tagihan yang belum memiliki checkout aktif.'],
            ]);
        }
    }
}
