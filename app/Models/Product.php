<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Product extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'inventory_type',
        'description',
        'base_price',
        'deposit_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }
}
