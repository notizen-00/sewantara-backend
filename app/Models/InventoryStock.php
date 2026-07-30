<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class InventoryStock extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'branch_id',
        'quantity_total',
        'quantity_reserved',
        'quantity_rented',
        'quantity_maintenance',
        'quantity_damaged',
        'quantity_lost',
    ];

    protected function casts(): array
    {
        return [
            'quantity_total' => 'integer',
            'quantity_reserved' => 'integer',
            'quantity_rented' => 'integer',
            'quantity_maintenance' => 'integer',
            'quantity_damaged' => 'integer',
            'quantity_lost' => 'integer',
        ];
    }
}
