<?php

namespace App\Modules\Inventory\Application;

use App\Models\ProductPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ManageProductPrices
{
    public function paginate(
        ?int $productId,
        ?int $branchId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return ProductPrice::query()
            ->when($productId, fn ($query, int $value) => $query->where('product_id', $value))
            ->when($branchId, fn ($query, int $value) => $query->where('branch_id', $value))
            ->latest()
            ->paginate(min(max($perPage, 1), 100));
    }

    public function create(array $attributes): ProductPrice
    {
        $attributes['is_active'] ??= true;

        return ProductPrice::query()->create($attributes);
    }

    public function update(ProductPrice $productPrice, array $attributes): ProductPrice
    {
        $productPrice->update($attributes);

        return $productPrice->refresh();
    }

    public function delete(ProductPrice $productPrice): void
    {
        $productPrice->delete();
    }
}
