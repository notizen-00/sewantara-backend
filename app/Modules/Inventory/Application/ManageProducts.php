<?php

namespace App\Modules\Inventory\Application;

use App\Models\Branch;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManageProducts
{
    public function paginate(
        ?string $search,
        ?int $categoryId,
        ?string $inventoryType,
        ?bool $isActive,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return Product::query()
            ->with(['category', 'images'])
            ->withCount('units')
            ->when($search, fn ($query, string $value) => $query->where(
                fn ($query) => $query
                    ->where('name', 'ilike', "%{$value}%")
                    ->orWhere('sku', 'ilike', "%{$value}%")
                    ->orWhere('brand', 'ilike', "%{$value}%")
                    ->orWhere('model', 'ilike', "%{$value}%"),
            ))
            ->when($categoryId, fn ($query, int $value) => $query->where('category_id', $value))
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

        return Product::create($attributes)->load('images');
    }

    public function detail(Product $product): Product
    {
        $product->load(['category', 'units', 'images']);
        $stockByBranch = $this->stockByBranch($product);

        $product->setAttribute('stock_by_branch', $stockByBranch);
        $product->setAttribute('stock_summary', $this->stockSummary($stockByBranch));

        return $product;
    }

    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->refresh()->load('images');
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stockByBranch(Product $product): array
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
        $currentBranchId = app()->bound('currentBranch')
            ? (int) app('currentBranch')->getKey()
            : null;

        if ($product->inventory_type === 'quantity') {
            $stocks = InventoryStock::query()
                ->where('product_id', $product->getKey())
                ->get()
                ->keyBy('branch_id');

            return $branches->map(function (Branch $branch) use (
                $stocks,
                $currentBranchId,
            ): array {
                $stock = $stocks->get($branch->getKey());

                return [
                    'branch' => $branch,
                    'is_current_branch' => (int) $branch->getKey() === $currentBranchId,
                    'quantity_total' => $stock?->quantity_total ?? 0,
                    'quantity_available' => $stock?->quantity_available ?? 0,
                    'quantity_reserved' => $stock?->quantity_reserved ?? 0,
                    'quantity_rented' => $stock?->quantity_rented ?? 0,
                    'quantity_maintenance' => $stock?->quantity_maintenance ?? 0,
                    'quantity_damaged' => $stock?->quantity_damaged ?? 0,
                    'quantity_lost' => $stock?->quantity_lost ?? 0,
                    'quantity_inactive' => 0,
                ];
            })->all();
        }

        $counts = ProductUnit::query()
            ->where('product_id', $product->getKey())
            ->select(['branch_id', 'status', DB::raw('COUNT(*) as aggregate')])
            ->groupBy('branch_id', 'status')
            ->get()
            ->groupBy('branch_id');

        return $branches->map(function (Branch $branch) use (
            $counts,
            $currentBranchId,
        ): array {
            $branchCounts = $counts->get($branch->getKey(), collect())
                ->pluck('aggregate', 'status')
                ->map(fn ($value): int => (int) $value);
            $maintenance = $branchCounts->get('maintenance', 0)
                + $branchCounts->get('cleaning', 0);

            return [
                'branch' => $branch,
                'is_current_branch' => (int) $branch->getKey() === $currentBranchId,
                'quantity_total' => $branchCounts->sum(),
                'quantity_available' => $branchCounts->get('available', 0),
                'quantity_reserved' => $branchCounts->get('reserved', 0),
                'quantity_rented' => $branchCounts->get('rented', 0),
                'quantity_maintenance' => $maintenance,
                'quantity_damaged' => $branchCounts->get('damaged', 0),
                'quantity_lost' => $branchCounts->get('lost', 0),
                'quantity_inactive' => $branchCounts->get('inactive', 0),
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $stockByBranch
     * @return array<string, int>
     */
    private function stockSummary(array $stockByBranch): array
    {
        $fields = [
            'quantity_total',
            'quantity_available',
            'quantity_reserved',
            'quantity_rented',
            'quantity_maintenance',
            'quantity_damaged',
            'quantity_lost',
            'quantity_inactive',
        ];

        return collect($fields)->mapWithKeys(
            fn (string $field): array => [
                $field => array_sum(array_column($stockByBranch, $field)),
            ],
        )->all();
    }
}
