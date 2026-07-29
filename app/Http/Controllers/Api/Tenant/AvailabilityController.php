<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\Bookings\Application\CheckAvailability;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request, CheckAvailability $checkAvailability)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'uuid'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'branch_id' => ['nullable', 'uuid'],
        ]);

        $availableUnits = $checkAvailability->execute(
            app('currentTenant')->id,
            $validated,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'available' => $availableUnits->isNotEmpty(),
                'available_count' => $availableUnits->count(),
                'units' => $availableUnits,
            ],
        ]);
    }
}
