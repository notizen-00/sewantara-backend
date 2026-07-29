<?php

namespace App\Modules\Inventory\Application;

use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ManageProductUnits
{
    public function paginate(?string $productId, ?string $status, int $perPage = 20): LengthAwarePaginator
    {
        return ProductUnit::query()
            ->when($productId, fn ($query, string $value) => $query->where('product_id', $value))
            ->when($status, fn ($query, string $value) => $query->where('status', $value))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $attributes): ProductUnit
    {
        $attributes['status'] ??= 'available';
        $attributes['condition'] ??= 'good';

        return ProductUnit::create($attributes);
    }
}
