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
    ];
}
