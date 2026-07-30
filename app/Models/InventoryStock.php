<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected $appends = [
        'quantity_available',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getQuantityAvailableAttribute(): int
    {
        return $this->quantity_total
            - $this->quantity_reserved
            - $this->quantity_rented
            - $this->quantity_maintenance
            - $this->quantity_damaged
            - $this->quantity_lost;
    }
}
