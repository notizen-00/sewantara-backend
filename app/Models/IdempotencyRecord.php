<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class IdempotencyRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'idempotency_key',
        'endpoint',
        'request_hash',
        'status',
        'response_status',
        'response_body',
        'resource_type',
        'resource_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_body' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
