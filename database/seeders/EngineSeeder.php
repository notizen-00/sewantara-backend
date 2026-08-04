<?php

namespace Database\Seeders;

use App\Models\Engine;
use Illuminate\Database\Seeder;

class EngineSeeder extends Seeder
{
    /**
     * @return array<int, array{code: string, name: string, description: string, monthly_price: string, is_core: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'rental',
                'name' => 'Rental Engine',
                'description' => 'Penyewaan kendaraan, peralatan, dan akomodasi (mobil, motor, kamera, kos, villa, apartemen).',
                'monthly_price' => '0.00',
                'is_core' => true,
            ],
            [
                'code' => 'booking',
                'name' => 'Booking Engine',
                'description' => 'Pemesanan ruang dan layanan berbasis sesi atau jam (PS per jam, studio, lapangan, karaoke).',
                'monthly_price' => '0.00',
                'is_core' => true,
            ],
            [
                'code' => 'membership',
                'name' => 'Membership Engine',
                'description' => 'Keanggotaan gym, coworking, dan paket bulanan. Harga bersifat contoh, belum final.',
                'monthly_price' => '50000.00',
                'is_core' => false,
            ],
            [
                'code' => 'sales',
                'name' => 'Sales Engine',
                'description' => 'Penjualan barang seperti snack dan merchandise. Harga bersifat contoh, belum final.',
                'monthly_price' => '50000.00',
                'is_core' => false,
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $sortOrder => $definition) {
            Engine::query()->updateOrCreate(
                ['code' => $definition['code']],
                [...$definition, 'sort_order' => $sortOrder + 1, 'is_active' => true],
            );
        }
    }
}
