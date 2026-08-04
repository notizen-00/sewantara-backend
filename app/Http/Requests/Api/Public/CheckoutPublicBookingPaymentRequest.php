<?php

namespace App\Http\Requests\Api\Public;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutPublicBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            '_idempotency_key' => [
                'required',
                'string',
                'max:64',
                'regex:/^(?:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}|[0-9A-HJKMNP-TV-Z]{26})$/',
            ],
            'tracking_token' => ['required', 'string', 'min:32', 'max:128'],
            'payment_method' => ['required', 'string', 'in:qris,midtrans'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            '_idempotency_key' => trim((string) $this->header('Idempotency-Key')),
            'tracking_token' => trim((string) $this->header('X-Tracking-Token')),
        ]);
    }
}
