<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Application\ManageProductUnits;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductUnitController extends Controller
{
    public function index(Request $request, ManageProductUnits $productUnits)
    {
        $units = $productUnits->paginate(
            $request->integer('product_id') ?: null,
            $request->string('status')->toString() ?: null,
        );

        return response()->json(['success' => true, 'data' => $units]);
    }

    public function store(Request $request, ManageProductUnits $productUnits)
    {
        $tenantId = app('currentTenant')->id;
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'unit_code' => ['required', 'string', 'max:100', Rule::unique('product_units', 'unit_code')->where('tenant_id', $tenantId)],
            'barcode' => ['nullable', 'string', 'max:150', Rule::unique('product_units', 'barcode')->where('tenant_id', $tenantId)],
            'qr_code' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['available', 'reserved', 'rented', 'cleaning', 'maintenance', 'damaged', 'lost', 'inactive'])],
            'condition' => ['nullable', 'string', 'max:30'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'current_meter' => ['nullable', 'integer', 'min:0'],
            'meter_unit' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product unit berhasil dibuat.',
            'data' => $productUnits->create(
                $validated,
                $request->user()?->id,
            ),
        ], 201);
    }
}
