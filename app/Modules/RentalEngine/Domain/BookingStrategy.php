<?php

namespace App\Modules\RentalEngine\Domain;

enum BookingStrategy: string
{
    case DateRange = 'date_range';
    case Queue = 'queue';
    case Session = 'session';
}
