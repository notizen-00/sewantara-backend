<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ProductMovement extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'product_unit_id',
        'booking_id',
        'type',
        'from_status',
        'to_status',
        'from_branch_id',
        'to_branch_id',
        'notes',
        'occurred_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
