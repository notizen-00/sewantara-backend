<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

class Domain extends BaseDomain
{
    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
        'verification_status',
        'verification_token',
        'verified_at',
        'ssl_status',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $domain): void {
            if (! $domain->type || $domain->isDirty('domain')) {
                $hostname = strtolower(trim((string) $domain->domain, '.'));
                $base = strtolower(trim((string) config(
                    'public-api.tenant_base_domain',
                    config('tenancy.tenant_base_domain'),
                ), '.'));
                $domain->type = $base !== ''
                    && ($hostname === $base
                        || str_ends_with($hostname, '.'.$base))
                    ? 'subdomain'
                    : 'custom_domain';
            }

            if (! $domain->status || $domain->isDirty('verification_status')) {
                $domain->status = $domain->verification_status === 'verified'
                    ? 'active'
                    : 'pending';
            }
        });
    }
}
