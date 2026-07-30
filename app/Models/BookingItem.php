<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class BookingItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'product_id',
        'product_name',
        'sku',
        'inventory_type',
        'pricing_type',
        'quantity',
        'duration',
        'unit_price',
        'subtotal',
        'deposit_amount',
        'discount_amount',
        'total_amount',
        'notes',
    ];
}
