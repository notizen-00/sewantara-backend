<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use App\Modules\Inventory\Domain\StockAdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustInventoryStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (string) tenant('id');

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists(Product::class, 'id')->where('tenant_id', $tenantId),
            ],
            'branch_id' => ['prohibited'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason_type' => ['required', Rule::enum(StockAdjustmentReason::class)],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:reason_type,other_in,other_out',
            ],
        ];
    }
}
