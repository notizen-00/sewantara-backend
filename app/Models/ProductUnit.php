<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ProductUnit extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'branch_id',
        'unit_code',
        'barcode',
        'qr_code',
        'serial_number',
        'status',
        'condition',
        'purchased_at',
        'purchase_price',
    ];
}
