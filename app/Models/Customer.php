<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Customer extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'birth_date',
        'gender',
        'status',
        'notes',
    ];
}
