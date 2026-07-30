<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Tenancy\Application\ManageTenants;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(ManageTenants $tenants)
    {
        return response()->json([
            'success' => true,
            'data' => $tenants->paginate(),
        ]);
    }

    public function store(Request $request, ManageTenants $tenants)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'alpha_dash', Rule::unique('tenants', 'slug')],
            'business_type' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('domains', 'domain')],
        ]);

        $tenant = $tenants->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Akun usaha berhasil dibuat.',
            'data' => $tenant,
        ], 201);
    }

    public function show(Tenant $tenant, ManageTenants $tenants)
    {
        return response()->json([
            'success' => true,
            'data' => $tenants->detail($tenant),
        ]);
    }
}
