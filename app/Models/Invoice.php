<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Invoice extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'invoice_number',
        'status',
        'total_amount',
        'issued_at',
        'due_at',
    ];
}
