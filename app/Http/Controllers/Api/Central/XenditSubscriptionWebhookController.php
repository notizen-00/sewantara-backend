<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Modules\SubscriptionBilling\Application\ConfirmSubscriptionPayment;
use App\Modules\SubscriptionBilling\Application\ExpireSubscriptionPayment;
use App\Modules\SubscriptionBilling\Infrastructure\Xendit\XenditWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XenditSubscriptionWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        XenditWebhookVerifier $verifier,
        ConfirmSubscriptionPayment $confirmPayment,
        ExpireSubscriptionPayment $expirePayment,
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
        $isExpired = $event === 'payment_session.expired';

        if ($isPaid || $isExpired) {
            $expectedStatus = $isPaid ? 'COMPLETED' : 'EXPIRED';
            abort_unless(
                is_string($data['reference_id'] ?? null)
                    && is_string($data['payment_session_id'] ?? null)
                    && is_numeric($data['amount'] ?? null)
                    && is_string($data['currency'] ?? null)
                    && ($data['session_type'] ?? null) === 'PAY'
                    && ($data['status'] ?? null) === $expectedStatus,
                422,
                'Data pembayaran Xendit tidak lengkap.',
            );

            $metadata = [
                'gross_amount' => (string) $data['amount'],
                'currency' => strtoupper((string) $data['currency']),
                'xendit' => $payload,
            ];

            if ($isPaid) {
                $confirmPayment->execute(
                    paymentNumber: $data['reference_id'],
                    gateway: 'xendit',
                    gatewayReference: isset($data['payment_id'])
                        ? (string) $data['payment_id']
                        : (string) $data['payment_session_id'],
                    gatewaySessionReference: (string) $data['payment_session_id'],
                    metadata: $metadata,
                );
            } else {
                $expirePayment->execute(
                    paymentNumber: $data['reference_id'],
                    gateway: 'xendit',
                    gatewaySessionReference: (string) $data['payment_session_id'],
                    metadata: $metadata,
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => match (true) {
                $isPaid => 'Pembayaran langganan berhasil dikonfirmasi.',
                $isExpired => 'Pembayaran langganan kedaluwarsa.',
                default => 'Notifikasi pembayaran diterima.',
            },
        ]);
    }
}
