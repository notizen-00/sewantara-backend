<?php

namespace App\Http\Requests\PublicApi;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

class AvailabilityRequest extends PublicReadRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeIntegers(['duration_minutes', 'quantity']);
        $normalized = ['quantity' => $this->input('quantity', 1)];

        foreach (['start', 'end', 'date'] as $key) {
            if ($this->filled($key)) {
                $value = $this->input($key);
                $normalized[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'start' => [
                'nullable',
                'required_without:date',
                'required_with:end',
                'date_format:Y-m-d',
            ],
            'end' => [
                'nullable',
                'required_with:start',
                'date_format:Y-m-d',
                'after:start',
            ],
            'date' => [
                'nullable',
                'required_without:start',
                'date_format:Y-m-d',
            ],
            'duration_minutes' => [
                'nullable',
                'required_with:date',
                'integer',
                'min:15',
                'max:1440',
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $hasRange = $this->filled('start') || $this->filled('end');
                $hasSlot = $this->filled('date')
                    || $this->filled('duration_minutes');

                if ($hasRange && $hasSlot) {
                    $validator->errors()->add(
                        'date',
                        'Gunakan rentang start/end atau date/duration_minutes, bukan keduanya.',
                    );
                }

                if ($this->filled('duration_minutes') && ! $this->filled('date')) {
                    $validator->errors()->add(
                        'duration_minutes',
                        'Duration minutes hanya dapat digunakan bersama date.',
                    );
                }

                if (! $this->filled('start')
                    || ! $this->filled('end')
                    || $validator->errors()->has('start')
                    || $validator->errors()->has('end')) {
                    return;
                }

                $start = CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    (string) $this->input('start'),
                );
                $end = CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    (string) $this->input('end'),
                );

                if ($start !== false && $end !== false
                    && $start->diffInDays($end) > 366) {
                    $validator->errors()->add(
                        'end',
                        'Rentang ketersediaan maksimum adalah 366 hari.',
                    );
                }
            },
        ];
    }

    protected function allowedQueryKeys(): array
    {
        return [
            'start',
            'end',
            'date',
            'duration_minutes',
            'quantity',
        ];
    }
}
