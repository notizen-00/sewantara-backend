<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Invoice extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'status',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
        ];
    }
}
