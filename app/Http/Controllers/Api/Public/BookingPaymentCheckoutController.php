<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Requests\Api\Public\CheckoutPublicBookingPaymentRequest;
use App\Modules\PublicApi\Application\PublicPayments;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use Illuminate\Http\JsonResponse;

class BookingPaymentCheckoutController extends PublicTransactionalController
{
    public function __invoke(
        CheckoutPublicBookingPaymentRequest $request,
        string $bookingCode,
        PublicPayments $payments,
    ): JsonResponse {
        $attributes = $request->validated();

        try {
            $outcome = $payments->checkoutForTrackedBooking(
                $this->tenantId($request),
                $bookingCode,
                $attributes['tracking_token'],
                $attributes['payment_method'],
                $attributes['_idempotency_key'],
                ['payment_method' => $attributes['payment_method']],
            );

            return $this->success($request, $outcome->data, $outcome->status);
        } catch (PublicApiException $exception) {
            return $this->error($request, $exception);
        }
    }
}
