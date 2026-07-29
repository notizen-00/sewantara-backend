<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class InventoryStock extends Model
{
    use BelongsToTenant, HasUuids;

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
}
