<?php

namespace App\Modules\Reporting\Application;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tenant;

class GetDashboardReport
{
    public function execute(Tenant $tenant, int $branchId): array
    {
        return [
            'tenant' => $tenant->only(['id', 'name', 'slug', 'business_type', 'timezone', 'currency']),
            'summary' => [
                'products' => Product::query()->count(),
                'customers' => Customer::query()->count(),
                'active_bookings' => Booking::query()
                    ->where('branch_id', $branchId)
                    ->whereIn('status', ['pending', 'confirmed', 'ready', 'ongoing'])
                    ->count(),
                'revenue_paid' => Payment::query()
                    ->whereIn(
                        'booking_id',
                        Booking::query()
                            ->select('id')
                            ->where('branch_id', $branchId),
                    )
                    ->where('status', 'paid')
                    ->where('type', '!=', 'deposit')
                    ->sum('amount'),
                'deposit_held' => Payment::query()
                    ->whereIn(
                        'booking_id',
                        Booking::query()
                            ->select('id')
                            ->where('branch_id', $branchId),
                    )
                    ->where('status', 'paid')
                    ->where('type', 'deposit')
                    ->sum('amount'),
            ],
        ];
    }
}
