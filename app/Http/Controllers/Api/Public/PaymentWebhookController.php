<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\HandleCentralPaymentWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        HandleCentralPaymentWebhook $handler,
    ): JsonResponse {
        if ((int) $request->server('CONTENT_LENGTH', 0) > 262_144) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PAYLOAD_TOO_LARGE'],
            ], 413);
        }

        try {
            $result = $handler->execute($provider, $request->all());
        } catch (InvalidArgumentException $exception) {
            Log::warning('payment_webhook_rejected', [
                'provider' => $provider,
                'request_id' => $request->attributes->get('request_id'),
                'reason' => class_basename($exception),
            ]);

            return response()->json([
                'success' => false,
                'error' => ['code' => 'WEBHOOK_REJECTED'],
            ], 401);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'error' => ['code' => 'WEBHOOK_TEMPORARILY_UNAVAILABLE'],
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'accepted' => $result['accepted'],
                'duplicate' => $result['duplicate'],
            ],
        ], $result['reference'] === null ? 202 : 200);
    }
}
