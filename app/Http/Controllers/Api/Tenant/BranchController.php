<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Application\ManageBranches;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(ManageBranches $branches)
    {
        return response()->json(['success' => true, 'data' => $branches->paginate()]);
    }

    public function store(Request $request, ManageBranches $branches)
    {
        $tenantId = app('currentTenant')->id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', Rule::unique('branches', 'code')->where('tenant_id', $tenantId)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cabang berhasil dibuat.',
            'data' => $branches->create($validated),
        ], 201);
    }
}
