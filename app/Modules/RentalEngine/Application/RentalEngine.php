<?php

namespace App\Modules\RentalEngine\Application;

use App\Models\RentalConfiguration;
use App\Modules\RentalEngine\Domain\AllocationStrategy;
use App\Modules\RentalEngine\Domain\BookingStrategy;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class RentalEngine
{
    /** @var array<string, RentalConfiguration> */
    private array $configurations = [];

    public function configuration(string $engineCode): RentalConfiguration
    {
        return $this->configurations[$engineCode] ??= RentalConfiguration::query()
            ->where('engine_code', $engineCode)
            ->firstOrFail();
    }

    public function prepareBooking(string $engineCode, array $attributes): array
    {
        $configuration = $this->configuration($engineCode);
        $channel = $attributes['booking_channel'] ?? 'walk_in';

        if ($channel === 'online' && ! $configuration->allow_online_booking) {
            throw ValidationException::withMessages([
                'booking_channel' => ['Pemesanan daring dinonaktifkan untuk usaha ini.'],
            ]);
        }

        if ($channel === 'walk_in' && ! $configuration->allow_walk_in) {
            throw ValidationException::withMessages([
                'booking_channel' => ['Pemesanan langsung di lokasi dinonaktifkan untuk usaha ini.'],
            ]);
        }

        $start = CarbonImmutable::parse($attributes['start_at']);
        $end = filled($attributes['end_at'] ?? null)
            ? CarbonImmutable::parse($attributes['end_at'])
            : null;

        if ($configuration->booking_strategy === BookingStrategy::DateRange) {
            if ($end === null || $end <= $start) {
                throw ValidationException::withMessages([
                    'end_at' => ['Tanggal selesai wajib diisi setelah tanggal mulai.'],
                ]);
            }
        } else {
            $slot = $configuration->slot_duration_minutes;

            if ($slot === null) {
                throw ValidationException::withMessages([
                    'slot_duration_minutes' => ['Durasi waktu penyewaan belum dikonfigurasi.'],
                ]);
            }

            $end ??= $start->addMinutes($slot);

            if ($end <= $start
                || $start->diffInMinutes($end) % $slot !== 0) {
                throw ValidationException::withMessages([
                    'end_at' => ["Durasi pemesanan harus merupakan kelipatan {$slot} menit."],
                ]);
            }
        }

        $attributes['start_at'] = $start;
        $attributes['end_at'] = $end;
        $attributes['booking_channel'] = $channel;

        return $attributes;
    }

    public function pricingType(string $engineCode): string
    {
        return $this->configuration($engineCode)->rental_model->pricingType();
    }

    public function usesAutoAssignment(string $engineCode): bool
    {
        return $this->configuration($engineCode)->allocation_strategy
            === AllocationStrategy::AutoAssign;
    }

    public function usesRealtimeAvailability(string $engineCode): bool
    {
        return $this->configuration($engineCode)->realtime_availability;
    }

    public function billableDuration(
        string $engineCode,
        mixed $startAt,
        mixed $endAt,
        int $priceDuration,
    ): int {
        $start = CarbonImmutable::parse($startAt);
        $end = CarbonImmutable::parse($endAt);
        $minutes = max(1, (int) ceil($start->diffInMinutes($end)));
        $baseMinutes = match ($this->configuration($engineCode)->rental_model->value) {
            'per_hour' => 60,
            'per_day' => 1440,
            'session' => $this->configuration($engineCode)->slot_duration_minutes ?? $minutes,
        };

        return max(
            1,
            (int) ceil($minutes / ($baseMinutes * max(1, $priceDuration))),
        );
    }
}
