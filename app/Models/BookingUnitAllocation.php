<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class BookingUnitAllocation extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'booking_item_id',
        'product_unit_id',
        'start_at',
        'end_at',
        'status',
        'allocated_at',
        'checked_out_at',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'allocated_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }
}
