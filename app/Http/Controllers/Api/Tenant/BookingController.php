<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Modules\Bookings\Application\ManageBookings;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request, ManageBookings $bookings)
    {
        return response()->json([
            'success' => true,
            'data' => $bookings->paginate(
                $request->string('status')->toString() ?: null,
            ),
        ]);
    }

    public function store(Request $request, ManageBookings $bookings)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'branch_id' => ['nullable', 'uuid'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_ids' => ['nullable', 'array'],
            'items.*.unit_ids.*' => ['uuid'],
        ]);

        $booking = $bookings->create(
            app('currentTenant')->id,
            $request->user()?->id,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking berhasil dibuat tanpa overlap.',
            'data' => $booking,
        ], 201);
    }

    public function show(Booking $booking, ManageBookings $bookings)
    {
        return response()->json([
            'success' => true,
            'data' => $bookings->detail($booking),
        ]);
    }
}
