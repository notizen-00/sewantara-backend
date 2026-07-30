<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Booking extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'customer_id',
        'created_by',
        'booking_number',
        'start_at',
        'end_at',
        'actual_start_at',
        'actual_end_at',
        'status',
        'booking_channel',
        'fulfillment_type',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'delivery_fee',
        'deposit_amount',
        'charge_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'payment_status',
        'customer_notes',
        'internal_notes',
        'confirmed_at',
        'cancelled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BookingUnitAllocation::class);
    }
}
