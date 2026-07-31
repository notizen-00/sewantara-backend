<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\HandlePaymentGatewayNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $gateway,
        HandlePaymentGatewayNotification $handler,
    ): JsonResponse {
        try {
            $payment = $handler->execute($gateway, $request->all());
        } catch (InvalidArgumentException $exception) {
            abort(403, $exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi pembayaran berhasil diproses.',
            'data' => [
                'payment_number' => $payment->payment_number,
                'status' => $payment->status,
            ],
        ]);
    }
}
