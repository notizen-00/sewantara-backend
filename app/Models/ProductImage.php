<?php

namespace App\Models;

use App\Support\TenantPrivateMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ProductImage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'image_path',
        'alt_text',
        'is_primary',
        'sort_order',
        'public_id',
    ];

    protected $appends = [
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return app(TenantPrivateMedia::class)->url($this->image_path);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereHas(
            'product',
            fn (Builder $products): Builder => $products->publiclyVisible(),
        );
    }

    protected static function booted(): void
    {
        static::creating(function (self $image): void {
            if (Schema::connection($image->getConnectionName())
                ->hasColumn($image->getTable(), 'public_id')) {
                $image->public_id ??= (string) Str::uuid();
            }
        });
    }
}
