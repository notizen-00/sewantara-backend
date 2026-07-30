<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class TenantOnboarding extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_onboarding';

    protected $fillable = [
        'tenant_id',
        'status',
        'current_step',
        'completed_steps',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
