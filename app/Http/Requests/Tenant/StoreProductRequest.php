<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use App\Modules\ProductEngine\Domain\EngineCode;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (string) tenant('id');

        return [
            'category_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'engine_code' => ['required', Rule::enum(EngineCode::class)],
            'product_type' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $engineCode = EngineCode::tryFrom((string) $this->input('engine_code'));

                    if ($engineCode === null) {
                        return;
                    }

                    $allowed = array_map(fn ($type) => $type->value, $engineCode->productTypes());

                    if (! in_array($value, $allowed, true)) {
                        $fail("Tipe produk tidak sesuai dengan engine {$engineCode->value}.");
                    }
                },
            ],
            'name' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                'alpha_dash',
                Rule::unique(Product::class, 'slug')
                    ->where('tenant_id', $tenantId),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique(Product::class, 'sku')
                    ->where('tenant_id', $tenantId),
            ],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'inventory_type' => [
                'required',
                Rule::in(['serialized', 'quantity']),
            ],
            'default_pricing_type' => [
                'required',
                Rule::in(['hourly', 'daily', 'weekly', 'monthly', 'event', 'custom']),
            ],
            'minimum_rental_duration' => ['nullable', 'integer', 'min:1'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'late_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'specifications' => ['nullable', 'array'],
            'specifications.*' => ['nullable'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'booking_rules' => ['nullable', 'string', 'max:5000'],
            'cancellation_policy' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
