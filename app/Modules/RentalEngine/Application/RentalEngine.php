<?php

namespace App\Modules\RentalEngine\Application;

use App\Models\RentalConfiguration;
use App\Modules\RentalEngine\Domain\AllocationStrategy;
use App\Modules\RentalEngine\Domain\BookingStrategy;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class RentalEngine
{
    private ?RentalConfiguration $configuration = null;

    public function configuration(): RentalConfiguration
    {
        return $this->configuration ??= RentalConfiguration::query()->firstOrFail();
    }

    public function prepareBooking(array $attributes): array
    {
        $configuration = $this->configuration();
        $channel = $attributes['booking_channel'] ?? 'walk_in';

        if ($channel === 'online' && ! $configuration->allow_online_booking) {
            throw ValidationException::withMessages([
                'booking_channel' => ['Online booking dinonaktifkan oleh tenant.'],
            ]);
        }

        if ($channel === 'walk_in' && ! $configuration->allow_walk_in) {
            throw ValidationException::withMessages([
                'booking_channel' => ['Walk-in booking dinonaktifkan oleh tenant.'],
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
                    'slot_duration_minutes' => ['Durasi slot rental belum dikonfigurasi.'],
                ]);
            }

            $end ??= $start->addMinutes($slot);

            if ($end <= $start
                || $start->diffInMinutes($end) % $slot !== 0) {
                throw ValidationException::withMessages([
                    'end_at' => ["Durasi booking harus merupakan kelipatan {$slot} menit."],
                ]);
            }
        }

        $attributes['start_at'] = $start;
        $attributes['end_at'] = $end;
        $attributes['booking_channel'] = $channel;

        return $attributes;
    }

    public function pricingType(): string
    {
        return $this->configuration()->rental_model->pricingType();
    }

    public function usesAutoAssignment(): bool
    {
        return $this->configuration()->allocation_strategy
            === AllocationStrategy::AutoAssign;
    }

    public function usesRealtimeAvailability(): bool
    {
        return $this->configuration()->realtime_availability;
    }

    public function billableDuration(
        mixed $startAt,
        mixed $endAt,
        int $priceDuration,
    ): int {
        $start = CarbonImmutable::parse($startAt);
        $end = CarbonImmutable::parse($endAt);
        $minutes = max(1, (int) ceil($start->diffInMinutes($end)));
        $baseMinutes = match ($this->configuration()->rental_model->value) {
            'per_hour' => 60,
            'per_day' => 1440,
            'session' => $this->configuration()->slot_duration_minutes ?? $minutes,
        };

        return max(
            1,
            (int) ceil($minutes / ($baseMinutes * max(1, $priceDuration))),
        );
    }
}
