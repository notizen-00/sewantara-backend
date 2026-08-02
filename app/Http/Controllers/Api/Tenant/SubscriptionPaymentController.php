<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Modules\SubscriptionBilling\Application\CreateSubscriptionCheckout;
use App\Modules\SubscriptionBilling\Application\GetSubscriptionPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPaymentController extends Controller
{
    public function checkout(
        Request $request,
        CreateSubscriptionCheckout $createCheckout,
    ): JsonResponse {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();
        $result = $createCheckout->execute($tenant, [
            'name' => $user?->name,
            'email' => $user?->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checkout pembayaran langganan berhasil dibuat.',
            'data' => [
                'payment' => $this->paymentData($result['payment']),
                'checkout' => [
                    'gateway' => $result['payment']->gateway,
                    'token' => $result['checkout']->token,
                    'redirect_url' => $result['checkout']->redirectUrl,
                ],
            ],
            'meta' => null,
        ], 201);
    }

    public function show(
        Request $request,
        string $payment,
        GetSubscriptionPayment $getPayment,
    ): JsonResponse {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $subscriptionPayment = $getPayment->execute($tenant, $payment);

        return response()->json([
            'success' => true,
            'message' => 'Status pembayaran langganan berhasil diambil.',
            'data' => [
                'payment' => $this->paymentData($subscriptionPayment),
            ],
            'meta' => null,
        ]);
    }

    /** @return array<string, mixed> */
    private function paymentData(SubscriptionPayment $payment): array
    {
        return [
            'id' => $payment->getKey(),
            'payment_number' => $payment->payment_number,
            'gateway' => $payment->gateway,
            'gateway_reference' => $payment->gateway_reference,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'updated_at' => $payment->updated_at?->toIso8601String(),
        ];
    }
}
