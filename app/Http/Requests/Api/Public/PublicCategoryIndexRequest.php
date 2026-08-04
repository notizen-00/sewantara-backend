<?php

namespace App\Http\Requests\Api\Public;

use Illuminate\Foundation\Http\FormRequest;

class PublicCategoryIndexRequest extends FormRequest
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
                'max:100',
            ],

            'parent' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],

            'only_parents' => [
                'nullable',
                'boolean',
            ],

            'with_children' => [
                'nullable',
                'boolean',
            ],

            'with_product_count' => [
                'nullable',
                'boolean',
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

            'parent' => $this->filled('parent')
                ? strtolower(
                    trim((string) $this->input('parent')),
                )
                : null,

            'only_parents' => $this->boolean(
                'only_parents',
            ),

            'with_children' => $this->boolean(
                'with_children',
            ),

            'with_product_count' => $this->has(
                'with_product_count',
            )
                ? $this->boolean('with_product_count')
                : true,
        ]);
    }
}
