<?php

namespace App\Http\Requests\Api\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'category' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],

            'min_price' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'max_price' => [
                'nullable',
                'integer',
                'min:0',
                'gte:min_price',
            ],

            'booking_mode' => [
                'nullable',
                Rule::in([
                    'date_range',
                    'daily',
                    'hourly',
                    'time_slot',
                    'package',
                ]),
            ],

            'featured' => [
                'nullable',
                'boolean',
            ],

            'sort' => [
                'nullable',
                Rule::in([
                    'recommended',
                    'newest',
                    'price_asc',
                    'price_desc',
                    'name_asc',
                    'popular',
                ]),
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search')
                ? trim((string) $this->input('search'))
                : null,

            'category' => $this->filled('category')
                ? strtolower(trim((string) $this->input('category')))
                : null,

            'featured' => $this->has('featured')
                ? $this->boolean('featured')
                : null,

            'sort' => $this->input('sort', 'recommended'),

            'page' => (int) $this->input('page', 1),

            'per_page' => (int) $this->input('per_page', 20),
        ]);
    }
}
