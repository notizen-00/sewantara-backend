<?php

namespace App\Modules\Tenancy\Application;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManageTenants
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Tenant::query()->latest()->paginate($perPage);
    }

    public function create(array $attributes): Tenant
    {
        return DB::transaction(function () use ($attributes): Tenant {
            $tenant = Tenant::create([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'] ?? Str::slug($attributes['name']),
                'business_type' => $attributes['business_type'] ?? null,
                'email' => $attributes['email'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'address' => $attributes['address'] ?? null,
                'status' => 'active',
                'activated_at' => now(),
            ]);

            if (! blank($attributes['domain'] ?? null)) {
                Domain::create([
                    'tenant_id' => $tenant->id,
                    'domain' => $attributes['domain'],
                    'is_primary' => true,
                ]);
            }

            return $tenant->load('domains');
        });
    }

    public function detail(Tenant $tenant): Tenant
    {
        return $tenant->load('domains');
    }
}
