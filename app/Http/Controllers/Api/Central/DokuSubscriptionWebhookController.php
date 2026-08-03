<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Modules\SubscriptionBilling\Application\ConfirmSubscriptionPayment;
use App\Modules\SubscriptionBilling\Infrastructure\Doku\DokuWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DokuSubscriptionWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        DokuWebhookVerifier $verifier,
        ConfirmSubscriptionPayment $confirmPayment,
    ): JsonResponse {
        abort_unless(
            $verifier->verify($request),
            403,
            'Signature webhook DOKU tidak valid.',
        );

        $payload = $request->all();
        $invoiceNumber = data_get($payload, 'order.invoice_number');
        $amount = data_get($payload, 'order.amount');
        $currency = data_get($payload, 'order.currency', 'IDR');
        $status = strtoupper((string) data_get($payload, 'transaction.status'));

        abort_unless(
            is_string($invoiceNumber) && $invoiceNumber !== ''
                && is_numeric($amount)
                && is_string($currency),
            422,
            'Payload pembayaran DOKU tidak lengkap.',
        );

        if ($status === 'SUCCESS') {
            $confirmPayment->execute(
                paymentNumber: $invoiceNumber,
                gateway: 'doku',
                gatewayReference: (string) (
                    data_get($payload, 'transaction.original_request_id')
                    ?? $request->header('Request-Id')
                ),
                gatewaySessionReference: null,
                metadata: [
                    'gross_amount' => (string) $amount,
                    'currency' => strtoupper($currency),
                    'doku' => $payload,
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => $status === 'SUCCESS'
                ? 'Pembayaran langganan berhasil dikonfirmasi.'
                : 'Notifikasi DOKU diterima.',
        ]);
    }
}
