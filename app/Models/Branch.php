<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Branch extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'email',
        'phone',
        'address',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
