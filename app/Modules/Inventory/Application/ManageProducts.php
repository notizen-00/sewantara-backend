<?php

namespace App\Modules\Inventory\Application;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ManageProducts
{
    public function paginate(
        ?string $search,
        ?string $categoryId,
        ?string $inventoryType,
        ?bool $isActive,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return Product::query()
            ->with('category')
            ->withCount('units')
            ->when($search, fn ($query, string $value) => $query->where(
                fn ($query) => $query
                    ->where('name', 'ilike', "%{$value}%")
                    ->orWhere('sku', 'ilike', "%{$value}%")
                    ->orWhere('brand', 'ilike', "%{$value}%")
                    ->orWhere('model', 'ilike', "%{$value}%"),
            ))
            ->when($categoryId, fn ($query, string $value) => $query->where('category_id', $value))
            ->when($inventoryType, fn ($query, string $value) => $query->where('inventory_type', $value))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->latest()
            ->paginate(min(max($perPage, 1), 100));
    }

    public function create(array $attributes): Product
    {
        $attributes['slug'] ??= Str::slug($attributes['name']);
        $attributes['slug'] = $this->uniqueSlug($attributes['slug']);
        $attributes['minimum_rental_duration'] ??= 1;
        $attributes['deposit_amount'] ??= 0;
        $attributes['late_fee_amount'] ??= 0;
        $attributes['is_featured'] ??= false;
        $attributes['is_active'] ??= true;

        return Product::create($attributes);
    }

    public function detail(Product $product): Product
    {
        return $product->load(['category', 'units']);
    }

    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    private function uniqueSlug(string $value): string
    {
        $baseSlug = Str::slug($value) ?: 'product';
        $slug = $baseSlug;
        $suffix = 1;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }
}
