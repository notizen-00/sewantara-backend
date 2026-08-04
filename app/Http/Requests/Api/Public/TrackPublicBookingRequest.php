<?php

namespace App\Http\Requests\Api\Public;

use App\Modules\PublicApi\Support\CustomerContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackPublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'verifier' => ['required', 'array', 'array:type,value'],
            'verifier.type' => ['required', Rule::in(['phone', 'email'])],
            'verifier.value' => ['required', 'string', 'max:150'],
            'tracking_token' => ['required', 'string', 'min:32', 'max:128'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $verifier = $this->input('verifier');

        if (! is_array($verifier) || ! is_string($verifier['value'] ?? null)) {
            return;
        }

        $verifier['value'] = ($verifier['type'] ?? null) === 'email'
            ? CustomerContact::email($verifier['value'])
            : CustomerContact::phone($verifier['value']);
        $this->merge(['verifier' => $verifier]);
    }
}
