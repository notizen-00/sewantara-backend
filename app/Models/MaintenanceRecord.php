<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class MaintenanceRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_unit_id',
        'type',
        'title',
        'description',
        'vendor',
        'cost',
        'scheduled_at',
        'started_at',
        'completed_at',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
