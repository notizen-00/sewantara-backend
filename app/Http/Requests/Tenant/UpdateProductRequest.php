<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (string) tenant('id');
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->getKey() : null;

        return [
            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['sometimes', 'string', 'max:200'],
            'slug' => [
                'sometimes',
                'string',
                'max:200',
                'alpha_dash',
                Rule::unique(Product::class, 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($productId),
            ],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique(Product::class, 'sku')
                    ->where('tenant_id', $tenantId)
                    ->ignore($productId),
            ],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'inventory_type' => [
                'sometimes',
                Rule::in(['serialized', 'quantity']),
            ],
            'default_pricing_type' => [
                'sometimes',
                Rule::in(['hourly', 'daily', 'weekly', 'monthly', 'event', 'custom']),
            ],
            'minimum_rental_duration' => ['sometimes', 'integer', 'min:1'],
            'deposit_amount' => ['sometimes', 'numeric', 'min:0'],
            'late_fee_amount' => ['sometimes', 'numeric', 'min:0'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
