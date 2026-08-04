<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
        'public_id',
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
        'public_web_enabled',
        'locale',
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
            'public_id',
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
            'public_web_enabled',
            'locale',
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
            'public_web_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tenant): void {
            if (Schema::connection($tenant->getConnectionName())
                ->hasColumn($tenant->getTable(), 'public_id')) {
                $tenant->public_id ??= (string) Str::uuid();
            }
        });
    }
}
