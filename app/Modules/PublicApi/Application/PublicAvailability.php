<?php

namespace App\Modules\PublicApi\Application;

use App\Models\BookingUnitAllocation;
use App\Models\Branch;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class PublicAvailability
{
    public function assertAvailable(
        Product $product,
        Branch $branch,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int $quantity,
        bool $lock = false,
    ): void {
        if ($product->inventory_type === 'serialized') {
            $available = $this->serializedQuery(
                $product,
                $branch,
                $startsAt,
                $endsAt,
            );

            if ($lock) {
                $available->lockForUpdate();
            }

            if ($available->limit($quantity)->pluck('id')->count() < $quantity) {
                $this->unavailable();
            }

            return;
        }

        if ($product->inventory_type === 'quantity') {
            $query = InventoryStock::query()
                ->where('product_id', $product->getKey())
                ->where('branch_id', $branch->getKey());

            if ($lock) {
                $query->lockForUpdate();
            }

            $stock = $query->first();

            if ($stock === null || $stock->quantity_available < $quantity) {
                $this->unavailable();
            }

            return;
        }

        throw new PublicApiException(
            'PRODUCT_UNAVAILABLE',
            'Jenis inventaris produk belum mendukung pemesanan publik.',
            409,
        );
    }

    /**
     * Locks and returns concrete serialized units for the booking transaction.
     *
     * @return array<int, int>
     */
    public function lockSerializedUnits(
        Product $product,
        Branch $branch,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int $quantity,
    ): array {
        if ($product->inventory_type !== 'serialized') {
            return [];
        }

        $ids = $this->serializedQuery(
            $product,
            $branch,
            $startsAt,
            $endsAt,
        )
            ->orderBy('id')
            ->lockForUpdate()
            ->limit($quantity)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if (count($ids) !== $quantity) {
            $this->unavailable();
        }

        return $ids;
    }

    /** @return Builder<ProductUnit> */
    private function serializedQuery(
        Product $product,
        Branch $branch,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): Builder {
        return ProductUnit::query()
            ->where('product_id', $product->getKey())
            ->where('branch_id', $branch->getKey())
            ->whereIn('status', ['available', 'reserved'])
            ->whereNotIn('id', function ($query) use ($startsAt, $endsAt): void {
                $query->select('product_unit_id')
                    ->from((new BookingUnitAllocation)->getTable())
                    ->whereIn('status', ['reserved', 'checked_out'])
                    ->where('start_at', '<', $endsAt)
                    ->where('end_at', '>', $startsAt);
            });
    }

    private function unavailable(): never
    {
        throw new PublicApiException(
            'PRODUCT_UNAVAILABLE',
            'Produk tidak tersedia untuk jumlah dan periode yang diminta.',
            409,
        );
    }
}
