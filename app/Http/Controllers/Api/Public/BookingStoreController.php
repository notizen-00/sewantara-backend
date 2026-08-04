<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Requests\Api\Public\StorePublicBookingRequest;
use App\Modules\PublicApi\Application\CreatePublicBooking;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use Illuminate\Http\JsonResponse;

class BookingStoreController extends PublicTransactionalController
{
    public function __invoke(
        StorePublicBookingRequest $request,
        CreatePublicBooking $bookings,
    ): JsonResponse {
        $attributes = $request->validated();
        $idempotencyKey = (string) $attributes['_idempotency_key'];
        unset($attributes['_idempotency_key']);

        try {
            $outcome = $bookings->execute(
                $this->tenantId($request),
                $idempotencyKey,
                $attributes,
            );

            return $this->success($request, $outcome->data, $outcome->status);
        } catch (PublicApiException $exception) {
            return $this->error($request, $exception);
        }
    }
}
