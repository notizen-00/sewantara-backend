<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Modules\Payments\Application\CreateBookingPaymentCheckout;
use App\Modules\Payments\Application\RecordBookingPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Booking $booking, RecordBookingPayment $recordPayment)
    {
        $this->ensureCurrentBranch($booking);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['down_payment', 'full_payment', 'rental', 'deposit', 'late_fee'])],
            'method' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'expired'])],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $recordPayment->execute(
            app('currentTenant')->id,
            $booking,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dicatat.',
            'data' => $payment,
        ], 201);
    }

    public function checkout(
        Request $request,
        Booking $booking,
        CreateBookingPaymentCheckout $createCheckout,
    ): JsonResponse {
        $this->ensureCurrentBranch($booking);

        $validated = $request->validate([
            'type' => ['required', Rule::in([
                'down_payment',
                'full_payment',
                'rental',
                'deposit',
                'late_fee',
            ])],
            'amount' => ['required', 'integer', 'min:1'],
            'gateway' => ['nullable', 'string', Rule::in(
                array_keys(config('payments.drivers', [])),
            )],
        ]);

        $gateway = $validated['gateway']
            ?? (string) config('payments.default', 'midtrans');
        $result = $createCheckout->execute(
            tenantId: (string) app('currentTenant')->id,
            booking: $booking,
            type: $validated['type'],
            amount: $validated['amount'],
            gatewayName: $gateway,
            notificationUrl: route('tenant.payments.webhooks.handle', [
                'tenant' => app('currentTenant')->getTenantKey(),
                'gateway' => $gateway,
            ]),
            createdBy: $request->user()?->getKey(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Checkout pembayaran berhasil dibuat.',
            'data' => [
                'payment' => $result->payment,
                'checkout' => [
                    'gateway' => $result->checkout->gateway,
                    'token' => $result->checkout->token,
                    'redirect_url' => $result->checkout->redirectUrl,
                ],
            ],
        ], 201);
    }

    private function ensureCurrentBranch(Booking $booking): void
    {
        abort_unless(
            (int) $booking->branch_id === (int) app('currentBranch')->getKey(),
            404,
        );
    }
}
