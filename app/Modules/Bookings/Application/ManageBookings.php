<?php

namespace App\Modules\Bookings\Application;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingUnitAllocation;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Modules\Inventory\Application\InventoryBookingLifecycle;
use App\Modules\RentalEngine\Application\RentalEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageBookings
{
    public function __construct(
        private readonly InventoryBookingLifecycle $inventory,
        private readonly RentalEngine $engine,
    ) {}

    public function paginate(
        ?string $status,
        int $branchId,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return Booking::query()
            ->with('items')
            ->where('branch_id', $branchId)
            ->when($status, fn ($query, string $value) => $query->where('status', $value))
            ->latest()
            ->paginate($perPage);
    }

    public function create(string $tenantId, ?string $creatorId, array $attributes): Booking
    {
        $attributes = $this->engine->prepareBooking($attributes);
        $customer = Customer::query()->findOrFail($attributes['customer_id']);

        if ($customer->status === 'blacklisted') {
            throw ValidationException::withMessages([
                'customer_id' => ['Pelanggan yang masuk daftar hitam tidak dapat membuat pesanan.'],
            ]);
        }

        return DB::transaction(function () use ($attributes, $creatorId, $tenantId): Booking {
            $subtotal = 0;
            $deposit = 0;
            $preparedItems = [];

            foreach ($attributes['items'] as $item) {
                $product = Product::query()->findOrFail($item['product_id']);
                [$unitPrice, $duration, $pricingType] = $this->resolvePricing(
                    $product,
                    $attributes,
                );
                $lineTotal = $unitPrice * $duration * (int) $item['quantity'];
                $lineDeposit = (float) $product->deposit_amount * (int) $item['quantity'];

                $preparedItems[] = compact(
                    'product',
                    'item',
                    'unitPrice',
                    'duration',
                    'pricingType',
                    'lineTotal',
                    'lineDeposit',
                );
                $subtotal += $lineTotal;
                $deposit += $lineDeposit;
            }

            $preparedItems = $this->assignUnitsWhenConfigured(
                $preparedItems,
                $attributes,
            );
            $this->guardUnitAvailability($preparedItems, $attributes);

            $booking = Booking::create([
                'tenant_id' => $tenantId,
                'branch_id' => $attributes['branch_id'] ?? null,
                'customer_id' => $attributes['customer_id'],
                'created_by' => $creatorId,
                'booking_number' => 'BKG-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'start_at' => $attributes['start_at'],
                'end_at' => $attributes['end_at'],
                'status' => 'pending',
                'booking_channel' => $attributes['booking_channel'],
                'fulfillment_type' => $attributes['fulfillment_type'] ?? 'pickup',
                'subtotal' => $subtotal,
                'deposit_amount' => $deposit,
                'total_amount' => $subtotal,
                'remaining_amount' => $subtotal,
                'payment_status' => 'unpaid',
                'customer_notes' => $attributes['customer_notes'] ?? null,
                'internal_notes' => $attributes['notes'] ?? null,
            ]);

            $this->createItemsAndAllocations($booking, $preparedItems, $attributes, $tenantId);
            $this->inventory->reserve($booking, $creatorId);
            $this->createInvoice($booking, $tenantId);
            $this->recordInitialStatus($booking, $tenantId, $creatorId);

            return $booking->load(['items', 'allocations']);
        });
    }

    public function detail(Booking $booking): Booking
    {
        return $booking->load(['items', 'allocations']);
    }

    private function guardUnitAvailability(array $preparedItems, array $attributes): void
    {
        foreach ($preparedItems as $prepared) {
            foreach ($prepared['item']['unit_ids'] ?? [] as $unitId) {
                $unit = ProductUnit::query()
                    ->where('product_id', $prepared['product']->id)
                    ->lockForUpdate()
                    ->findOrFail($unitId);

                if (! in_array($unit->status, ['available', 'reserved'], true)) {
                    throw ValidationException::withMessages([
                        'items' => ["Unit {$unit->unit_code} berstatus {$unit->status} dan tidak dapat dipesan."],
                    ]);
                }

                if (($attributes['branch_id'] ?? null) !== null
                    && (int) $unit->branch_id !== (int) $attributes['branch_id']) {
                    throw ValidationException::withMessages([
                        'items' => ["Unit {$unit->unit_code} tidak berada di cabang pemesanan."],
                    ]);
                }

                $overlap = BookingUnitAllocation::query()
                    ->where('product_unit_id', $unit->id)
                    ->whereIn('status', ['reserved', 'checked_out'])
                    ->where('start_at', '<', $attributes['end_at'])
                    ->where('end_at', '>', $attributes['start_at'])
                    ->exists();

                if ($overlap) {
                    throw ValidationException::withMessages([
                        'items' => ["Unit {$unit->unit_code} sudah teralokasi pada periode tersebut."],
                    ]);
                }
            }

            if ($prepared['product']->inventory_type === 'serialized'
                && count($prepared['item']['unit_ids'] ?? []) !== (int) $prepared['item']['quantity']) {
                throw ValidationException::withMessages([
                    'items' => ["Jumlah unit {$prepared['product']->name} harus sesuai dengan kuantitas yang dipesan."],
                ]);
            }
        }
    }

    private function createItemsAndAllocations(
        Booking $booking,
        array $preparedItems,
        array $attributes,
        string $tenantId,
    ): void {
        foreach ($preparedItems as $prepared) {
            $bookingItem = BookingItem::create([
                'tenant_id' => $tenantId,
                'booking_id' => $booking->id,
                'product_id' => $prepared['product']->id,
                'product_name' => $prepared['product']->name,
                'sku' => $prepared['product']->sku,
                'inventory_type' => $prepared['product']->inventory_type,
                'pricing_type' => $prepared['pricingType'],
                'quantity' => $prepared['item']['quantity'],
                'duration' => $prepared['duration'],
                'unit_price' => $prepared['unitPrice'],
                'subtotal' => $prepared['lineTotal'],
                'deposit_amount' => $prepared['lineDeposit'],
                'total_amount' => $prepared['lineTotal'],
                'notes' => $prepared['item']['notes'] ?? null,
            ]);

            foreach ($prepared['item']['unit_ids'] ?? [] as $unitId) {
                BookingUnitAllocation::create([
                    'tenant_id' => $tenantId,
                    'booking_id' => $booking->id,
                    'booking_item_id' => $bookingItem->id,
                    'product_unit_id' => $unitId,
                    'start_at' => $attributes['start_at'],
                    'end_at' => $attributes['end_at'],
                    'status' => 'reserved',
                    'allocated_at' => now(),
                ]);
            }
        }
    }

    private function createInvoice(Booking $booking, string $tenantId): void
    {
        Invoice::create([
            'tenant_id' => $tenantId,
            'booking_id' => $booking->id,
            'invoice_number' => 'INV-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
            'issue_date' => today(),
            'due_date' => today(),
            'status' => 'draft',
            'subtotal' => $booking->subtotal,
            'total_amount' => $booking->total_amount,
            'remaining_amount' => $booking->remaining_amount,
        ]);
    }

    private function recordInitialStatus(Booking $booking, string $tenantId, ?string $creatorId): void
    {
        DB::table('booking_status_histories')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => 'pending',
            'notes' => null,
            'changed_by' => $creatorId,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{0: float, 1: int, 2: string}
     */
    private function resolvePricing(Product $product, array $attributes): array
    {
        $pricingType = $this->engine->pricingType();
        $price = DB::table('product_prices')
            ->where('product_id', $product->getKey())
            ->where('pricing_type', $pricingType)
            ->where('is_active', true)
            ->where(function ($query) use ($attributes): void {
                $query->where('branch_id', $attributes['branch_id'] ?? null)
                    ->orWhereNull('branch_id');
            })
            ->orderByRaw('branch_id IS NULL')
            ->first(['price', 'duration']);

        if ($price === null) {
            throw ValidationException::withMessages([
                'items' => ["Harga aktif {$product->name} belum tersedia."],
            ]);
        }

        $duration = $this->engine->billableDuration(
            $attributes['start_at'],
            $attributes['end_at'],
            (int) $price->duration,
        );

        return [(float) $price->price, $duration, $pricingType];
    }

    private function assignUnitsWhenConfigured(
        array $preparedItems,
        array $attributes,
    ): array {
        if (! $this->engine->usesAutoAssignment()) {
            return $preparedItems;
        }

        foreach ($preparedItems as &$prepared) {
            if ($prepared['product']->inventory_type !== 'serialized'
                || ! empty($prepared['item']['unit_ids'])) {
                continue;
            }

            $units = ProductUnit::query()
                ->where('product_id', $prepared['product']->getKey())
                ->when(
                    $attributes['branch_id'] ?? null,
                    fn ($query, int $branchId) => $query->where('branch_id', $branchId),
                )
                ->whereIn('status', ['available', 'reserved'])
                ->whereNotIn('id', function ($query) use ($attributes): void {
                    $query->select('product_unit_id')
                        ->from('booking_unit_allocations')
                        ->whereIn('status', ['reserved', 'checked_out'])
                        ->where('start_at', '<', $attributes['end_at'])
                        ->where('end_at', '>', $attributes['start_at']);
                })
                ->lockForUpdate()
                ->limit((int) $prepared['item']['quantity'])
                ->pluck('id')
                ->all();

            if (count($units) !== (int) $prepared['item']['quantity']) {
                throw ValidationException::withMessages([
                    'items' => ["Unit {$prepared['product']->name} yang tersedia tidak mencukupi."],
                ]);
            }

            $prepared['item']['unit_ids'] = $units;
        }
        unset($prepared);

        return $preparedItems;
    }
}
