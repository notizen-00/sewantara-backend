<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Modules\SubscriptionBilling\Application\ConfirmSubscriptionPayment;
use App\Modules\SubscriptionBilling\Infrastructure\Xendit\XenditWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XenditSubscriptionWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        XenditWebhookVerifier $verifier,
        ConfirmSubscriptionPayment $confirmPayment,
    ): JsonResponse {
        abort_unless(
            $verifier->verify($request->header('x-callback-token')),
            403,
            'Token webhook Xendit tidak valid.',
        );

        $payload = $request->all();
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? null;

        abort_unless(is_string($event) && is_array($data), 422, 'Payload Xendit tidak valid.');

        $isPaid = $event === 'payment_session.completed';

        if ($isPaid) {
            abort_unless(
                is_string($data['reference_id'] ?? null)
                    && is_numeric($data['amount'] ?? null),
                422,
                'Data pembayaran Xendit tidak lengkap.',
            );

            $confirmPayment->execute(
                paymentNumber: $data['reference_id'],
                gatewayReference: isset($data['payment_id'])
                    ? (string) $data['payment_id']
                    : (isset($data['payment_session_id'])
                        ? (string) $data['payment_session_id']
                        : null),
                metadata: [
                    'gross_amount' => (string) $data['amount'],
                    'xendit' => $payload,
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => $isPaid
                ? 'Pembayaran langganan berhasil dikonfirmasi.'
                : 'Notifikasi pembayaran diterima.',
        ]);
    }
}
