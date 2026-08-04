<?php

namespace App\Http\Requests\PublicApi;

use Illuminate\Validation\Rule;

class CatalogIndexRequest extends PublicReadRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeIntegers(['min_price', 'max_price', 'page', 'per_page']);
        $sort = $this->input('sort');
        $normalized = [
            'sort' => ! $this->filled('sort')
                ? 'recommended'
                : (is_string($sort) ? strtolower(trim($sort)) : $sort),
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 20),
        ];

        foreach (['q', 'category', 'booking_mode', 'available_from', 'available_until'] as $key) {
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
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:150', 'alpha_dash:ascii'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0', 'gte:min_price'],
            'booking_mode' => [
                'nullable',
                Rule::in([
                    'date_range',
                    'daily',
                    'hourly',
                    'time_slot',
                    'quantity_only',
                    'serialized_unit',
                    'appointment',
                    'queue',
                ]),
            ],
            'available_from' => [
                'nullable',
                'required_with:available_until',
                'date_format:Y-m-d',
            ],
            'available_until' => [
                'nullable',
                'required_with:available_from',
                'date_format:Y-m-d',
                'after:available_from',
            ],
            'sort' => [
                'required',
                Rule::in([
                    'recommended',
                    'newest',
                    'price_asc',
                    'price_desc',
                    'name_asc',
                    'popular',
                ]),
            ],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function allowedQueryKeys(): array
    {
        return [
            'q',
            'category',
            'min_price',
            'max_price',
            'booking_mode',
            'available_from',
            'available_until',
            'sort',
            'page',
            'per_page',
        ];
    }
}
