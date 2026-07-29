<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravelcm\Subscriptions\Traits\HasPlanSubscriptions;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Concerns\HasScopedValidationRules;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;
    use HasPlanSubscriptions;
    use HasScopedValidationRules;
    use SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'business_type',
        'email',
        'phone',
        'address',
        'logo_path',
        'timezone',
        'currency',
        'status',
        'data',
        'activated_at',
        'suspended_at',
        'provisioning_status',
        'provisioned_at',
        'provisioning_error',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'business_type',
            'email',
            'phone',
            'address',
            'logo_path',
            'timezone',
            'currency',
            'status',
            'activated_at',
            'suspended_at',
            'created_at',
            'updated_at',
            'deleted_at',
            'provisioning_status',
            'provisioned_at',
            'provisioning_error',
        ];
    }

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'provisioned_at' => 'datetime',
        ];
    }
}
