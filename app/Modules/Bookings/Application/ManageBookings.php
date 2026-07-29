<?php

namespace App\Modules\Bookings\Application;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingUnitAllocation;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageBookings
{
    public function paginate(?string $status, int $perPage = 20): LengthAwarePaginator
    {
        return Booking::query()
            ->with('items')
            ->when($status, fn ($query, string $value) => $query->where('status', $value))
            ->latest()
            ->paginate($perPage);
    }

    public function create(string $tenantId, ?string $creatorId, array $attributes): Booking
    {
        $customer = Customer::query()->findOrFail($attributes['customer_id']);

        if ($customer->status === 'blacklisted') {
            throw ValidationException::withMessages([
                'customer_id' => ['Customer blacklist tidak dapat membuat booking.'],
            ]);
        }

        return DB::transaction(function () use ($attributes, $creatorId, $tenantId): Booking {
            $subtotal = 0;
            $deposit = 0;
            $preparedItems = [];

            foreach ($attributes['items'] as $item) {
                $product = Product::query()->findOrFail($item['product_id']);
                $lineTotal = (float) $product->base_price * (int) $item['quantity'];
                $lineDeposit = (float) $product->deposit_amount * (int) $item['quantity'];

                $preparedItems[] = compact('product', 'item', 'lineTotal', 'lineDeposit');
                $subtotal += $lineTotal;
                $deposit += $lineDeposit;
            }

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
                'subtotal_amount' => $subtotal,
                'deposit_amount' => $deposit,
                'total_amount' => $subtotal,
                'remaining_amount' => $subtotal,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $this->createItemsAndAllocations($booking, $preparedItems, $attributes, $tenantId);
            $this->createInvoice($booking, $tenantId);
            $this->recordInitialStatus($booking, $tenantId, $creatorId);

            return $booking->load('items');
        });
    }

    public function detail(Booking $booking): Booking
    {
        return $booking->load('items');
    }

    private function guardUnitAvailability(array $preparedItems, array $attributes): void
    {
        foreach ($preparedItems as $prepared) {
            foreach ($prepared['item']['unit_ids'] ?? [] as $unitId) {
                $unit = ProductUnit::query()
                    ->where('product_id', $prepared['product']->id)
                    ->lockForUpdate()
                    ->findOrFail($unitId);

                if ($unit->status === 'maintenance') {
                    throw ValidationException::withMessages([
                        'items' => ["Unit {$unit->unit_code} sedang maintenance dan tidak dapat dibooking."],
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
                'quantity' => $prepared['item']['quantity'],
                'unit_price' => $prepared['product']->base_price,
                'deposit_amount' => $prepared['lineDeposit'],
                'total_amount' => $prepared['lineTotal'],
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
            'status' => 'draft',
            'total_amount' => $booking->total_amount,
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
            'created_by' => $creatorId,
            'created_at' => now(),
        ]);
    }
}
