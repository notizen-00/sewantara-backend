<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class PaymentTransaction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'gateway',
        'transaction_id',
        'request_payload',
        'response_payload',
        'callback_payload',
        'signature_valid',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'callback_payload' => 'array',
            'signature_valid' => 'boolean',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
