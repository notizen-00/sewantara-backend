<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantInventorySeeder extends Seeder
{
    use SeedsDemoTenant;

    public function run(): void
    {
        $this->withinDemoTenant(function (string $tenantId): void {
            $branchId = $this->tenantRowId(
                'branches',
                $tenantId,
                'code',
                'JBR-01',
            );

            $cameraCategoryId = $this->upsertTenantRow(
                table: 'categories',
                tenantId: $tenantId,
                identity: ['slug' => 'kamera'],
                attributes: [
                    'parent_id' => null,
                    'name' => 'Kamera',
                    'description' => 'Kamera mirrorless dan DSLR.',
                    'image_path' => 'demo/categories/camera.jpg',
                    'sort_order' => 1,
                    'is_active' => true,
                    'deleted_at' => null,
                ],
            );
            $accessoryCategoryId = $this->upsertTenantRow(
                table: 'categories',
                tenantId: $tenantId,
                identity: ['slug' => 'aksesoris'],
                attributes: [
                    'parent_id' => null,
                    'name' => 'Aksesoris',
                    'description' => 'Aksesoris pendukung produksi.',
                    'image_path' => 'demo/categories/accessories.jpg',
                    'sort_order' => 2,
                    'is_active' => true,
                    'deleted_at' => null,
                ],
            );
            $lensCategoryId = $this->upsertTenantRow(
                table: 'categories',
                tenantId: $tenantId,
                identity: ['slug' => 'lensa'],
                attributes: [
                    'parent_id' => $cameraCategoryId,
                    'name' => 'Lensa',
                    'description' => 'Lensa kamera berbagai focal length.',
                    'image_path' => 'demo/categories/lens.jpg',
                    'sort_order' => 1,
                    'is_active' => true,
                    'deleted_at' => null,
                ],
            );

            $cameraId = $this->seedProduct(
                tenantId: $tenantId,
                categoryId: $cameraCategoryId,
                sku: 'CAM-SONY-A7IV',
                name: 'Sony Alpha A7 IV',
                slug: 'sony-alpha-a7-iv',
                brand: 'Sony',
                model: 'A7 IV',
                inventoryType: 'serialized',
                price: 450000,
                deposit: 1000000,
            );
            $lensId = $this->seedProduct(
                tenantId: $tenantId,
                categoryId: $lensCategoryId,
                sku: 'LENS-SONY-2470',
                name: 'Sony FE 24-70mm F2.8 GM II',
                slug: 'sony-fe-24-70mm-f28-gm-ii',
                brand: 'Sony',
                model: 'SEL2470GM2',
                inventoryType: 'serialized',
                price: 250000,
                deposit: 500000,
            );
            $tripodId = $this->seedProduct(
                tenantId: $tenantId,
                categoryId: $accessoryCategoryId,
                sku: 'ACC-TRIPOD-001',
                name: 'Tripod Video Heavy Duty',
                slug: 'tripod-video-heavy-duty',
                brand: 'Takara',
                model: 'VIT-234',
                inventoryType: 'quantity',
                price: 75000,
                deposit: 100000,
            );

            $this->seedSerializedUnit(
                $tenantId,
                $cameraId,
                $branchId,
                'CAM-A7IV-001',
                'SN-A7IV-0001',
            );
            $this->seedSerializedUnit(
                $tenantId,
                $cameraId,
                $branchId,
                'CAM-A7IV-002',
                'SN-A7IV-0002',
            );
            $this->seedSerializedUnit(
                $tenantId,
                $lensId,
                $branchId,
                'LENS-2470-001',
                'SN-2470-0001',
            );

            $this->upsertTenantRow(
                table: 'inventory_stocks',
                tenantId: $tenantId,
                identity: [
                    'product_id' => $tripodId,
                    'branch_id' => $branchId,
                ],
                attributes: [
                    'quantity_total' => 20,
                    'quantity_reserved' => 2,
                    'quantity_rented' => 0,
                    'quantity_maintenance' => 0,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                ],
            );

            $movementExists = DB::table('inventory_stock_movements')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $tripodId)
                ->where('type', 'initial_stock')
                ->exists();

            if (! $movementExists) {
                DB::table('inventory_stock_movements')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $tripodId,
                    'branch_id' => $branchId,
                    'booking_id' => null,
                    'type' => 'initial_stock',
                    'quantity' => 20,
                    'balance_before' => 0,
                    'balance_after' => 20,
                    'reference_type' => 'demo_seed',
                    'reference_id' => $tripodId,
                    'notes' => 'Stok awal data demo.',
                    'created_by' => null,
                    'occurred_at' => now()->subDay(),
                    'created_at' => now(),
                ]);
            }
        });
    }

    private function seedProduct(
        string $tenantId,
        string $categoryId,
        string $sku,
        string $name,
        string $slug,
        string $brand,
        string $model,
        string $inventoryType,
        int $price,
        int $deposit,
    ): string {
        $productId = $this->upsertTenantRow(
            table: 'products',
            tenantId: $tenantId,
            identity: ['sku' => $sku],
            attributes: [
                'category_id' => $categoryId,
                'name' => $name,
                'slug' => $slug,
                'brand' => $brand,
                'model' => $model,
                'description' => "Data demo {$name}.",
                'inventory_type' => $inventoryType,
                'default_pricing_type' => 'daily',
                'minimum_rental_duration' => 1,
                'deposit_amount' => $deposit,
                'late_fee_amount' => (int) round($price * 0.5),
                'is_featured' => true,
                'is_active' => true,
                'deleted_at' => null,
            ],
        );

        $this->upsertTenantRow(
            table: 'product_prices',
            tenantId: $tenantId,
            identity: [
                'product_id' => $productId,
                'branch_id' => $this->tenantRowId(
                    'branches',
                    $tenantId,
                    'code',
                    'JBR-01',
                ),
                'pricing_type' => 'daily',
            ],
            attributes: [
                'duration' => 1,
                'price' => $price,
                'start_at' => null,
                'end_at' => null,
                'is_active' => true,
            ],
        );

        $this->upsertTenantRow(
            table: 'product_images',
            tenantId: $tenantId,
            identity: [
                'product_id' => $productId,
                'sort_order' => 0,
            ],
            attributes: [
                'image_path' => "demo/products/{$slug}.jpg",
                'alt_text' => $name,
                'is_primary' => true,
            ],
        );

        return $productId;
    }

    private function seedSerializedUnit(
        string $tenantId,
        string $productId,
        string $branchId,
        string $unitCode,
        string $serialNumber,
    ): void {
        $this->upsertTenantRow(
            table: 'product_units',
            tenantId: $tenantId,
            identity: ['unit_code' => $unitCode],
            attributes: [
                'product_id' => $productId,
                'branch_id' => $branchId,
                'barcode' => "BAR-{$unitCode}",
                'qr_code' => "QR-{$unitCode}",
                'serial_number' => $serialNumber,
                'plate_number' => null,
                'condition' => 'good',
                'status' => 'available',
                'purchase_date' => now()->subYear()->toDateString(),
                'purchase_price' => 25000000,
                'current_meter' => null,
                'meter_unit' => null,
                'notes' => 'Unit demo siap disewa.',
                'deleted_at' => null,
            ],
        );
    }
}
