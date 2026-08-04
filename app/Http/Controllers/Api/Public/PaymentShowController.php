<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Requests\Api\Public\PublicPaymentStatusRequest;
use App\Modules\PublicApi\Application\PublicPayments;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use Illuminate\Http\JsonResponse;

class PaymentShowController extends PublicTransactionalController
{
    public function __invoke(
        PublicPaymentStatusRequest $request,
        string $publicPaymentId,
        PublicPayments $payments,
    ): JsonResponse {
        try {
            return $this->success(
                $request,
                $payments->status(
                    $publicPaymentId,
                    $request->validated('tracking_token'),
                ),
            );
        } catch (PublicApiException $exception) {
            return $this->error($request, $exception);
        }
    }
}
