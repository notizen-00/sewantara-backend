<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TenantEngine extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'engine_id',
        'is_enabled',
        'enabled_at',
        'disabled_at',
        'price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
            'price_snapshot' => 'decimal:2',
        ];
    }

    public function engine(): BelongsTo
    {
        return $this->belongsTo(Engine::class);
    }
}
