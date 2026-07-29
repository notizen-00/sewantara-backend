<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdomain' => strtolower((string) $this->input('subdomain')),
        ]);
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/',
                Rule::notIn(config('tenancy.reserved_subdomains', [])),
                Rule::unique('domains', 'domain'),
            ],
            'owner' => ['required', 'array'],
            'owner.name' => ['required', 'string', 'max:150'],
            'owner.email' => ['required', 'email:rfc,dns', 'max:150'],
            'owner.phone' => ['nullable', 'string', 'max:30'],
            'owner.password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
            'plan_id' => [
                'required',
                'integer',
                Rule::exists(config('laravel-subscriptions.tables.plans'), 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'billing_interval' => ['required', Rule::in(['month', 'year'])],
            'terms_accepted' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'subdomain.regex' => 'Subdomain hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
            'subdomain.not_in' => 'Subdomain tersebut tidak dapat digunakan.',
        ];
    }
}
