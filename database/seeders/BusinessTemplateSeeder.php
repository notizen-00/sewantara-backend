<?php

namespace Database\Seeders;

use App\Models\BusinessTemplate;
use Illuminate\Database\Seeder;

class BusinessTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::templates() as $template) {
            BusinessTemplate::query()->updateOrCreate(
                ['code' => $template['code']],
                $template,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function templates(): array
    {
        return [
            self::template('car_rental', 'Penyewaan Mobil', 'car', 'per_day', 'date_range', 'auto_assign', false, null, false, 10),
            self::template('motorcycle_rental', 'Penyewaan Sepeda Motor', 'motorcycle', 'per_day', 'date_range', 'auto_assign', false, null, false, 20),
            self::template('playstation_rental', 'Penyewaan PlayStation', 'gamepad-2', 'per_hour', 'queue', 'auto_assign', true, 60, true, 30),
            self::template('camera_rental', 'Penyewaan Kamera', 'camera', 'per_day', 'date_range', 'auto_assign', false, null, false, 40),
            self::template('camping_rental', 'Penyewaan Peralatan Berkemah', 'tent-tree', 'per_day', 'date_range', 'manual', false, null, true, 50),
            self::template('building_rental', 'Penyewaan Gedung', 'building-2', 'session', 'session', 'manual', false, 240, false, 60),
            self::template('sports_field', 'Penyewaan Lapangan Olahraga', 'goal', 'session', 'session', 'manual', false, 120, true, 70),
            self::template('music_studio', 'Studio Musik', 'mic-2', 'per_hour', 'queue', 'manual', true, 60, true, 80),
            self::template('custom', 'Konfigurasi Khusus', 'settings-2', 'per_day', 'date_range', 'manual', false, null, true, 999),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function template(
        string $code,
        string $name,
        string $icon,
        string $rentalModel,
        string $bookingStrategy,
        string $allocationStrategy,
        bool $waitingList,
        ?int $slotDuration,
        bool $extendBooking,
        int $sortOrder,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'description' => "Konfigurasi awal untuk usaha {$name}.",
            'icon' => $icon,
            'configuration' => [
                'rental_model' => $rentalModel,
                'booking_strategy' => $bookingStrategy,
                'allocation_strategy' => $allocationStrategy,
                'slot_duration_minutes' => $slotDuration,
                'enable_waiting_list' => $waitingList,
                'allow_walk_in' => true,
                'allow_online_booking' => true,
                'allow_extend_booking' => $extendBooking,
                'realtime_availability' => true,
                'auto_reminder' => true,
                'auto_cancel_unpaid' => false,
                'auto_cancel_minutes' => null,
                'engine_version' => 1,
            ],
            'version' => 1,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ];
    }
}
