<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Modules\SubscriptionBilling\Application\ConfirmSubscriptionPayment;
use App\Modules\SubscriptionBilling\Infrastructure\Midtrans\MidtransSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransSubscriptionWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MidtransSignatureVerifier $signature,
        ConfirmSubscriptionPayment $confirmPayment,
    ): JsonResponse {
        $payload = $request->all();

        abort_unless($signature->verify($payload), 403, 'Signature tidak valid.');

        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = (string) ($payload['fraud_status'] ?? 'accept');
        $isPaid = $transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && $fraudStatus === 'accept');

        if ($isPaid) {
            $confirmPayment->execute(
                paymentNumber: (string) $payload['order_id'],
                gatewayReference: isset($payload['transaction_id'])
                    ? (string) $payload['transaction_id']
                    : null,
                metadata: $payload,
            );
        }

        return response()->json([
            'success' => true,
            'message' => $isPaid
                ? 'Pembayaran subscription berhasil dikonfirmasi.'
                : 'Notifikasi pembayaran diterima.',
        ]);
    }
}
