<?php

namespace App\Http\Requests\PublicApi;

use App\Support\PublicApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class PublicReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(PublicApiResponse::error(
            $this,
            'VALIDATION_ERROR',
            'Data yang dikirim tidak valid.',
            422,
            fields: $validator->errors()->toArray(),
        ));
    }

    protected function normalizeIntegers(array $keys): void
    {
        $normalized = [];

        foreach ($keys as $key) {
            $value = $this->input($key);

            if (is_int($value)) {
                continue;
            }

            if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
                $normalized[$key] = (int) $value;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknown = array_values(array_diff(
                array_keys($this->query->all()),
                $this->allowedQueryKeys(),
            ));

            foreach ($unknown as $key) {
                $validator->errors()->add(
                    $key,
                    'Parameter query tidak didukung.',
                );
            }
        }];
    }

    /** @return list<string> */
    abstract protected function allowedQueryKeys(): array;
}
