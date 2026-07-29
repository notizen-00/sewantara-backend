<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Payment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'payment_number',
        'type',
        'method',
        'status',
        'amount',
        'gateway',
        'gateway_reference',
        'paid_at',
        'notes',
    ];
}
