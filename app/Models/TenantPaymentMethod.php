<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class TenantPaymentMethod extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'method',
        'is_enabled',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'configuration' => 'encrypted:array',
        ];
    }
}
