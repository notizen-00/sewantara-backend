<?php

namespace App\Models;

use App\Modules\RentalEngine\Domain\AllocationStrategy;
use App\Modules\RentalEngine\Domain\BookingStrategy;
use App\Modules\RentalEngine\Domain\RentalModel;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class RentalConfiguration extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'rental_model',
        'booking_strategy',
        'allocation_strategy',
        'slot_duration_minutes',
        'enable_waiting_list',
        'allow_walk_in',
        'allow_online_booking',
        'allow_extend_booking',
        'realtime_availability',
        'auto_reminder',
        'auto_cancel_unpaid',
        'auto_cancel_minutes',
        'engine_version',
    ];

    protected function casts(): array
    {
        return [
            'rental_model' => RentalModel::class,
            'booking_strategy' => BookingStrategy::class,
            'allocation_strategy' => AllocationStrategy::class,
            'slot_duration_minutes' => 'integer',
            'enable_waiting_list' => 'boolean',
            'allow_walk_in' => 'boolean',
            'allow_online_booking' => 'boolean',
            'allow_extend_booking' => 'boolean',
            'realtime_availability' => 'boolean',
            'auto_reminder' => 'boolean',
            'auto_cancel_unpaid' => 'boolean',
            'auto_cancel_minutes' => 'integer',
            'engine_version' => 'integer',
        ];
    }
}
