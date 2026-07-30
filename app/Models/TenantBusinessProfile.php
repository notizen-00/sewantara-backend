<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class TenantBusinessProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'template_code',
        'template_version',
        'business_name',
        'timezone',
        'currency',
        'operating_hours',
    ];

    protected function casts(): array
    {
        return [
            'template_version' => 'integer',
            'operating_hours' => 'array',
        ];
    }
}
