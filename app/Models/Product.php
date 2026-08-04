<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Product extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'brand',
        'model',
        'inventory_type',
        'default_pricing_type',
        'minimum_rental_duration',
        'description',
        'deposit_amount',
        'late_fee_amount',
        'is_featured',
        'is_active',
        'public_id',
        'is_public',
        'published_at',
        'specifications',
        'seo_title',
        'seo_description',
        'booking_rules',
        'cancellation_policy',
    ];

    protected function casts(): array
    {
        return [
            'deposit_amount' => 'decimal:2',
            'late_fee_amount' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'published_at' => 'datetime',
            'specifications' => 'array',
            'booking_rules' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            if (Schema::connection($product->getConnectionName())
                ->hasColumn($product->getTable(), 'public_id')
            ) {
                $product->public_id ??= (string) Str::uuid();
            }
        });
    }
}
