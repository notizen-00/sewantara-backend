<?php

namespace App\Http\Requests\Api\Public;

use App\Modules\PublicApi\Support\CustomerContact;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicBookingRequest extends FormRequest
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
            'quote_id' => ['required', 'uuid'],
            'customer' => ['required', 'array', 'array:name,phone,email'],
            'customer.name' => ['required', 'string', 'max:150'],
            'customer.phone' => ['required', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'customer.email' => ['nullable', 'email:rfc', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'agreement' => ['required', 'array', 'array:terms_accepted,privacy_accepted'],
            'agreement.terms_accepted' => ['required', 'accepted'],
            'agreement.privacy_accepted' => ['required', 'accepted'],
            'payment_method' => [
                'required',
                'string',
                'in:qris,midtrans',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $customer = $this->input('customer');

        if (is_array($customer)) {
            if (is_string($customer['name'] ?? null)) {
                $customer['name'] = trim($customer['name']);
            }

            if (is_string($customer['phone'] ?? null)) {
                $customer['phone'] = CustomerContact::phone($customer['phone']);
            }

            if (is_string($customer['email'] ?? null)) {
                $customer['email'] = CustomerContact::email($customer['email']);
            }
        }

        $this->merge([
            '_idempotency_key' => trim((string) $this->header('Idempotency-Key')),
            'customer' => $customer,
        ]);
    }
}
