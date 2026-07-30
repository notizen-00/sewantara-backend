<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Modules\Bookings\Application\ManageBookings;
use App\Modules\Bookings\Application\ManageBookingStatus;
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
            'customer_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'booking_channel' => ['nullable', 'in:walk_in,online'],
            'fulfillment_type' => ['nullable', 'in:pickup,delivery'],
            'customer_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_ids' => ['nullable', 'array'],
            'items.*.unit_ids.*' => ['integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $booking = $bookings->create(
            app('currentTenant')->id,
            $request->user()?->id,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Pemesanan berhasil dibuat tanpa benturan jadwal.',
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

    public function checkOut(
        Booking $booking,
        Request $request,
        ManageBookingStatus $statuses,
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Barang pesanan berhasil diserahkan kepada pelanggan.',
            'data' => $statuses->checkOut($booking, $request->user()?->id),
        ]);
    }

    public function return(
        Booking $booking,
        Request $request,
        ManageBookingStatus $statuses,
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Barang pesanan berhasil dikembalikan.',
            'data' => $statuses->return($booking, $request->user()?->id),
        ]);
    }

    public function cancel(
        Booking $booking,
        Request $request,
        ManageBookingStatus $statuses,
    ) {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pemesanan berhasil dibatalkan dan stok telah tersedia kembali.',
            'data' => $statuses->cancel(
                $booking,
                $request->user()?->id,
                $validated['notes'] ?? null,
            ),
        ]);
    }
}
