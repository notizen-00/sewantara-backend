<?php

namespace App\Http\Requests\Tenant;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferInventoryStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (string) tenant('id');
        $sourceBranchId = app('currentBranch')->getKey();

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists(Product::class, 'id')->where('tenant_id', $tenantId),
            ],
            'target_branch_id' => [
                'required',
                'integer',
                Rule::notIn([$sourceBranchId]),
                Rule::exists(Branch::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
