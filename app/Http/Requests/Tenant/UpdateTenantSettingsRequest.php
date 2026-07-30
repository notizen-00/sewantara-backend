<?php

namespace App\Http\Requests\Tenant;

use App\Modules\RentalEngine\Domain\AllocationStrategy;
use App\Modules\RentalEngine\Domain\BookingStrategy;
use App\Modules\RentalEngine\Domain\RentalModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'regular' => ['nullable', 'array'],
            'regular.timezone' => ['nullable', 'timezone'],
            'regular.currency' => ['nullable', 'string', 'size:3'],
            'regular.business_name' => ['nullable', 'string', 'max:150'],
            'regular.operating_hours' => ['nullable', 'array'],
            'regular.default_language' => ['nullable', 'string', 'max:10'],
            'regular.date_format' => ['nullable', 'string', 'max:30'],
            'regular.time_format' => ['nullable', 'string', 'max:30'],

            'branding' => ['nullable', 'array'],
            'branding.logo_url' => ['prohibited'],
            'branding.favicon_url' => ['prohibited'],
            'branding.invoice_logo_url' => ['prohibited'],
            'branding.primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'branding.secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'branch' => ['nullable', 'array'],
            'branch.name' => ['nullable', 'string', 'max:150'],
            'branch.email' => ['nullable', 'email', 'max:150'],
            'branch.phone' => ['nullable', 'string', 'max:30'],
            'branch.address' => ['nullable', 'string'],
            'branch.latitude' => ['nullable', 'numeric'],
            'branch.longitude' => ['nullable', 'numeric'],
            'branch.is_active' => ['nullable', 'boolean'],
            'branch.logo_url' => ['prohibited'],

            'rental_engine' => ['nullable', 'array'],
            'rental_engine.rental_model' => ['nullable', Rule::enum(RentalModel::class)],
            'rental_engine.booking_strategy' => ['nullable', Rule::enum(BookingStrategy::class)],
            'rental_engine.allocation_strategy' => ['nullable', Rule::enum(AllocationStrategy::class)],
            'rental_engine.slot_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
            'rental_engine.enable_waiting_list' => ['nullable', 'boolean'],
            'rental_engine.allow_walk_in' => ['nullable', 'boolean'],
            'rental_engine.allow_online_booking' => ['nullable', 'boolean'],
            'rental_engine.allow_extend_booking' => ['nullable', 'boolean'],
            'rental_engine.realtime_availability' => ['nullable', 'boolean'],
            'rental_engine.auto_reminder' => ['nullable', 'boolean'],
            'rental_engine.auto_cancel_unpaid' => ['nullable', 'boolean'],
            'rental_engine.auto_cancel_minutes' => [
                'nullable',
                'integer',
                'min:5',
                'required_if:rental_engine.auto_cancel_unpaid,true',
            ],
        ];
    }
}
