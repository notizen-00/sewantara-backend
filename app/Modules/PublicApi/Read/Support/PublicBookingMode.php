<?php

namespace App\Modules\PublicApi\Read\Support;

use App\Models\RentalConfiguration;
use BackedEnum;

final class PublicBookingMode
{
    public function resolve(?RentalConfiguration $configuration): string
    {
        $strategy = $this->value($configuration?->booking_strategy);
        $rentalModel = $this->value($configuration?->rental_model);

        return match ($strategy) {
            'queue' => 'queue',
            'session' => 'time_slot',
            'date_range' => match ($rentalModel) {
                'per_hour' => 'hourly',
                'per_day' => 'daily',
                default => 'date_range',
            },
            default => match ($rentalModel) {
                'per_hour' => 'hourly',
                'per_day' => 'daily',
                'session' => 'time_slot',
                default => 'date_range',
            },
        };
    }

    public function inventoryType(string $inventoryType): string
    {
        return match ($inventoryType) {
            'quantity' => 'pooled_stock',
            'serialized' => 'serialized',
            default => 'service_capacity',
        };
    }

    private function value(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }
}
