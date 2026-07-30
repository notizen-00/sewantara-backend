<?php

namespace App\Http\Requests\Tenant;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferProductUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (string) tenant('id');

        return [
            'target_branch_id' => [
                'required',
                'integer',
                Rule::notIn([app('currentBranch')->getKey()]),
                Rule::exists(Branch::class, 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
