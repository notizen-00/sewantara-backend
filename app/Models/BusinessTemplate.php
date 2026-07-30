<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'configuration',
        'version',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'version' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }
}
