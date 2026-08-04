<?php

namespace App\Modules\ProductEngine\Domain;

enum EngineCode: string
{
    case Rental = 'rental';
    case Booking = 'booking';
    case Membership = 'membership';
    case Sales = 'sales';

    /**
     * @return array<int, ProductType>
     */
    public function productTypes(): array
    {
        return match ($this) {
            self::Rental => [ProductType::Vehicle, ProductType::Equipment, ProductType::Accommodation],
            self::Booking => [ProductType::Space, ProductType::Service],
            self::Membership => [ProductType::Membership, ProductType::Package],
            self::Sales => [ProductType::Goods],
        };
    }

    public function isCore(): bool
    {
        return match ($this) {
            self::Rental, self::Booking => true,
            default => false,
        };
    }

    /**
     * Classifies a tenant's existing rental_configurations row as either the
     * Rental or Booking engine. Anything other than the classic date-range
     * rental strategy (queue or session based) reads as Booking-style usage
     * (e.g. PS-per-jam, studio, lapangan).
     */
    public static function fromRentalConfiguration(string $bookingStrategy): self
    {
        return $bookingStrategy === 'date_range' ? self::Rental : self::Booking;
    }
}
