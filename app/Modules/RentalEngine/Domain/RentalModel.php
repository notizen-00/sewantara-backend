<?php

namespace App\Modules\RentalEngine\Domain;

enum RentalModel: string
{
    case PerHour = 'per_hour';
    case PerDay = 'per_day';
    case Session = 'session';

    public function pricingType(): string
    {
        return match ($this) {
            self::PerHour => 'hourly',
            self::PerDay => 'daily',
            self::Session => 'event',
        };
    }
}
