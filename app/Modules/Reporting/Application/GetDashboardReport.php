<?php

namespace App\Modules\Reporting\Application;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tenant;

class GetDashboardReport
{
    public function execute(Tenant $tenant): array
    {
        return [
            'tenant' => $tenant->only(['id', 'name', 'slug', 'business_type', 'timezone', 'currency']),
            'summary' => [
                'products' => Product::query()->count(),
                'customers' => Customer::query()->count(),
                'active_bookings' => Booking::query()
                    ->whereIn('status', ['pending', 'confirmed', 'ready', 'ongoing'])
                    ->count(),
                'revenue_paid' => Payment::query()
                    ->where('status', 'paid')
                    ->where('type', '!=', 'deposit')
                    ->sum('amount'),
                'deposit_held' => Payment::query()
                    ->where('status', 'paid')
                    ->where('type', 'deposit')
                    ->sum('amount'),
            ],
        ];
    }
}
