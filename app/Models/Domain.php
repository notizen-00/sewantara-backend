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
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}
