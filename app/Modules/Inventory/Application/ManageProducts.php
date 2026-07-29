<?php

namespace App\Modules\Inventory\Application;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ManageProducts
{
    public function paginate(?string $search, int $perPage = 20): LengthAwarePaginator
    {
        return Product::query()
            ->withCount('units')
            ->when($search, fn ($query, string $value) => $query->where('name', 'ilike', "%{$value}%"))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $attributes): Product
    {
        $attributes['slug'] ??= Str::slug($attributes['name']);
        $attributes['deposit_amount'] ??= 0;
        $attributes['is_active'] ??= true;

        return Product::create($attributes);
    }

    public function detail(Product $product): Product
    {
        return $product->load('units');
    }

    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->refresh();
    }
}
