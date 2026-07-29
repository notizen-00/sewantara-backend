<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Booking extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'customer_id',
        'created_by',
        'booking_number',
        'start_at',
        'end_at',
        'status',
        'subtotal_amount',
        'discount_amount',
        'deposit_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }
}
