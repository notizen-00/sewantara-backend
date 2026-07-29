<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantBookingSeeder extends Seeder
{
    use SeedsDemoTenant;

    public const BOOKING_NUMBER = 'BOOK-DEMO-0001';

    public function run(): void
    {
        $this->withinDemoTenant(function (string $tenantId): void {
            $branchId = $this->tenantRowId('branches', $tenantId, 'code', 'JBR-01');
            $customerId = $this->tenantRowId(
                'customers',
                $tenantId,
                'phone',
                '081298765432',
            );
            $ownerId = (string) DB::table('users')
                ->where('email', DemoTenantRegistrationSeeder::OWNER_EMAIL)
                ->value('id');
            $cameraId = $this->tenantRowId(
                'products',
                $tenantId,
                'sku',
                'CAM-SONY-A7IV',
            );
            $tripodId = $this->tenantRowId(
                'products',
                $tenantId,
                'sku',
                'ACC-TRIPOD-001',
            );

            $startAt = now()->startOfDay()->addDays(2)->addHours(8);
            $endAt = $startAt->copy()->addDays(2);

            $bookingId = $this->upsertTenantRow(
                table: 'bookings',
                tenantId: $tenantId,
                identity: ['booking_number' => self::BOOKING_NUMBER],
                attributes: [
                    'branch_id' => $branchId,
                    'customer_id' => $customerId,
                    'created_by' => $ownerId,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'actual_start_at' => null,
                    'actual_end_at' => null,
                    'status' => 'confirmed',
                    'fulfillment_type' => 'pickup',
                    'subtotal' => 1200000,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'delivery_fee' => 0,
                    'deposit_amount' => 500000,
                    'charge_amount' => 0,
                    'total_amount' => 1200000,
                    'paid_amount' => 600000,
                    'remaining_amount' => 600000,
                    'payment_status' => 'partial',
                    'customer_notes' => 'Pengambilan pagi hari.',
                    'internal_notes' => 'Booking demo untuk pengujian alur rental.',
                    'confirmed_at' => now(),
                    'cancelled_at' => null,
                    'completed_at' => null,
                    'deleted_at' => null,
                ],
            );

            $cameraItemId = $this->seedBookingItem(
                tenantId: $tenantId,
                bookingId: $bookingId,
                productId: $cameraId,
                productName: 'Sony Alpha A7 IV',
                sku: 'CAM-SONY-A7IV',
                inventoryType: 'serialized',
                quantity: 1,
                duration: 2,
                unitPrice: 450000,
                subtotal: 900000,
                deposit: 400000,
            );
            $this->seedBookingItem(
                tenantId: $tenantId,
                bookingId: $bookingId,
                productId: $tripodId,
                productName: 'Tripod Video Heavy Duty',
                sku: 'ACC-TRIPOD-001',
                inventoryType: 'quantity',
                quantity: 2,
                duration: 2,
                unitPrice: 75000,
                subtotal: 300000,
                deposit: 100000,
            );

            $unitId = $this->tenantRowId(
                'product_units',
                $tenantId,
                'unit_code',
                'CAM-A7IV-001',
            );

            $this->upsertTenantRow(
                table: 'booking_unit_allocations',
                tenantId: $tenantId,
                identity: [
                    'booking_item_id' => $cameraItemId,
                    'product_unit_id' => $unitId,
                ],
                attributes: [
                    'booking_id' => $bookingId,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'status' => 'reserved',
                    'allocated_at' => now(),
                    'checked_out_at' => null,
                    'returned_at' => null,
                ],
            );

            DB::table('product_units')
                ->where('id', $unitId)
                ->update(['status' => 'reserved', 'updated_at' => now()]);

            if (! DB::table('booking_status_histories')
                ->where('booking_id', $bookingId)
                ->where('to_status', 'confirmed')
                ->exists()) {
                DB::table('booking_status_histories')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'booking_id' => $bookingId,
                    'from_status' => 'draft',
                    'to_status' => 'confirmed',
                    'notes' => 'Booking demo dikonfirmasi.',
                    'changed_by' => $ownerId,
                    'created_at' => now(),
                ]);
            }
        });
    }

    private function seedBookingItem(
        string $tenantId,
        string $bookingId,
        string $productId,
        string $productName,
        string $sku,
        string $inventoryType,
        int $quantity,
        int $duration,
        int $unitPrice,
        int $subtotal,
        int $deposit,
    ): string {
        return $this->upsertTenantRow(
            table: 'booking_items',
            tenantId: $tenantId,
            identity: [
                'booking_id' => $bookingId,
                'product_id' => $productId,
            ],
            attributes: [
                'product_name' => $productName,
                'sku' => $sku,
                'inventory_type' => $inventoryType,
                'pricing_type' => 'daily',
                'quantity' => $quantity,
                'duration' => $duration,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'deposit_amount' => $deposit,
                'total_amount' => $subtotal,
                'notes' => 'Item booking data demo.',
            ],
        );
    }
}
