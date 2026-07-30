<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Modules\Payments\Application\RecordBookingPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Booking $booking, RecordBookingPayment $recordPayment)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['down_payment', 'full_payment', 'rental', 'deposit', 'late_fee'])],
            'method' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'expired'])],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $recordPayment->execute(
            app('currentTenant')->id,
            $booking,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dicatat.',
            'data' => $payment,
        ], 201);
    }
}
