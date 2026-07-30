<?php

namespace App\Modules\Organization\Application;

use App\Models\Branch;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncBranchMasterData
{
    /**
     * Products and categories are tenant-wide master data, so only
     * branch-specific prices and empty stock structures need replication.
     *
     * @return array<string, mixed>
     */
    public function execute(
        Branch $source,
        Branch $target,
        bool $syncPrices = true,
        bool $prepareStocks = true,
        bool $overwritePrices = false,
    ): array {
        if ($source->is($target)) {
            throw ValidationException::withMessages([
                'target_branch' => ['Cabang tujuan harus berbeda dari cabang sumber.'],
            ]);
        }

        return DB::transaction(function () use (
            $source,
            $target,
            $syncPrices,
            $prepareStocks,
            $overwritePrices,
        ): array {
            $priceSummary = $syncPrices
                ? $this->syncPrices($source, $target, $overwritePrices)
                : ['created' => 0, 'updated' => 0, 'skipped' => 0];

            $stockStructuresCreated = $prepareStocks
                ? $this->prepareStockStructures($target)
                : 0;

            return [
                'source_branch' => $source,
                'target_branch' => $target,
                'shared_master_data' => [
                    'categories' => 'tenant_global',
                    'products' => 'tenant_global',
                ],
                'branch_specific_data' => [
                    'prices' => $priceSummary,
                    'stock_structures_created' => $stockStructuresCreated,
                    'stock_quantities_copied' => false,
                    'serialized_units_copied' => false,
                ],
            ];
        });
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    private function syncPrices(
        Branch $source,
        Branch $target,
        bool $overwrite,
    ): array {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        ProductPrice::query()
            ->where('branch_id', $source->getKey())
            ->orderBy('id')
            ->each(function (ProductPrice $sourcePrice) use (
                $target,
                $overwrite,
                &$summary,
            ): void {
                $targetPrice = ProductPrice::query()
                    ->where('product_id', $sourcePrice->product_id)
                    ->where('branch_id', $target->getKey())
                    ->where('pricing_type', $sourcePrice->pricing_type)
                    ->where('duration', $sourcePrice->duration)
                    ->where('start_at', $sourcePrice->getRawOriginal('start_at'))
                    ->where('end_at', $sourcePrice->getRawOriginal('end_at'))
                    ->first();

                $values = [
                    'price' => $sourcePrice->price,
                    'is_active' => $sourcePrice->is_active,
                ];

                if (! $targetPrice) {
                    ProductPrice::query()->create([
                        'tenant_id' => $target->tenant_id,
                        'product_id' => $sourcePrice->product_id,
                        'branch_id' => $target->getKey(),
                        'pricing_type' => $sourcePrice->pricing_type,
                        'duration' => $sourcePrice->duration,
                        'start_at' => $sourcePrice->getRawOriginal('start_at'),
                        'end_at' => $sourcePrice->getRawOriginal('end_at'),
                        ...$values,
                    ]);
                    $summary['created']++;

                    return;
                }

                if ($overwrite) {
                    $targetPrice->update($values);
                    $summary['updated']++;

                    return;
                }

                $summary['skipped']++;
            });

        return $summary;
    }

    private function prepareStockStructures(Branch $target): int
    {
        $created = 0;

        Product::query()
            ->where('inventory_type', 'quantity')
            ->where('is_active', true)
            ->pluck('id')
            ->each(function (int $productId) use ($target, &$created): void {
                $stock = InventoryStock::query()->firstOrCreate([
                    'tenant_id' => $target->tenant_id,
                    'product_id' => $productId,
                    'branch_id' => $target->getKey(),
                ]);

                if ($stock->wasRecentlyCreated) {
                    $created++;
                }
            });

        return $created;
    }
}
