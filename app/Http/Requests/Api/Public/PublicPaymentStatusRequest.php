<?php

namespace App\Http\Requests\Api\Public;

use Illuminate\Foundation\Http\FormRequest;

class PublicPaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tracking_token' => ['required', 'string', 'min:32', 'max:128'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tracking_token' => trim((string) $this->header('X-Tracking-Token')),
        ]);
    }
}
