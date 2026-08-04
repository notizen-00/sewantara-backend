<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'payment_number',
        'type',
        'method',
        'status',
        'amount',
        'gateway',
        'gateway_reference',
        'proof_path',
        'paid_at',
        'expired_at',
        'notes',
        'created_by',
        'public_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (Schema::connection($payment->getConnectionName())
                ->hasColumn($payment->getTable(), 'public_id')) {
                $payment->public_id ??= (string) Str::uuid();
            }
        });
    }
}
