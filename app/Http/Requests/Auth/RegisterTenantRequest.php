<?php

namespace App\Http\Requests\Auth;

use App\Models\BusinessTemplate;
use App\Models\CentralUser;
use App\Models\Domain;
use App\Modules\RegistrationVerification\Application\RegistrationOtp;
use App\Support\TenantHostname;
use Closure;
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
            'business_type' => [
                'required',
                'string',
                'max:100',
                Rule::exists(BusinessTemplate::class, 'code')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'subdomain' => [
                'required',
                'string',
                'min:2',
                'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                'not_regex:/--/',
                Rule::notIn(config('tenancy.reserved_subdomains', [])),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $hostname = TenantHostname::fromSubdomain((string) $value);

                    if (Domain::query()->where('domain', $hostname)->exists()) {
                        $fail('Subdomain tersebut sudah digunakan.');
                    }
                },
            ],
            'owner' => ['required', 'array'],
            'owner.name' => ['required', 'string', 'max:150'],
            'owner.email' => [
                'required',
                'email:rfc,dns',
                'max:150',
                Rule::unique(CentralUser::class, 'email'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! app(RegistrationOtp::class)->isVerified((string) $value)) {
                        $fail('Email belum diverifikasi. Silakan minta dan masukkan kode OTP terlebih dahulu.');
                    }
                },
            ],
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
