<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Requests\Api\Public\TrackPublicBookingRequest;
use App\Modules\PublicApi\Application\TrackPublicBooking;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use Illuminate\Http\JsonResponse;

class BookingTrackingController extends PublicTransactionalController
{
    public function __invoke(
        TrackPublicBookingRequest $request,
        string $bookingCode,
        TrackPublicBooking $tracking,
    ): JsonResponse {
        try {
            $validated = $request->validated();

            return $this->success($request, $tracking->execute(
                $bookingCode,
                $validated['verifier'],
                $validated['tracking_token'],
            ));
        } catch (PublicApiException $exception) {
            return $this->error($request, $exception);
        }
    }
}
