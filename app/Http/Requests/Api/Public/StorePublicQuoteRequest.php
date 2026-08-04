<?php

namespace App\Http\Requests\Api\Public;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid'],
            'variant_id' => ['nullable', 'uuid'],
            'booking' => ['required', 'array', 'array:starts_at,ends_at,quantity'],
            'booking.starts_at' => ['required', 'date'],
            'booking.ends_at' => ['required', 'date', 'after:booking.starts_at'],
            'booking.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'addons' => ['sometimes', 'array', 'max:20'],
            'addons.*' => ['array', 'array:id,quantity'],
            'addons.*.id' => ['required', 'uuid'],
            'addons.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $coupon = $this->input('coupon_code');

        if (is_string($coupon)) {
            $this->merge(['coupon_code' => strtoupper(trim($coupon)) ?: null]);
        }
    }
}
