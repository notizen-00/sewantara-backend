<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Category extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = ['tenant_id', 'parent_id', 'name', 'slug', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
