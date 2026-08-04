<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Modules\SalesEngine\Application\ManageSalesOrders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesOrderController extends Controller
{
    public function index(Request $request, ManageSalesOrders $orders): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $orders->paginate(
                $request->string('status')->toString() ?: null,
                $request->integer('per_page', 20),
            ),
        ]);
    }

    public function store(Request $request, ManageSalesOrders $orders): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('customers', 'id')
                    ->where('tenant_id', (string) app('currentTenant')->getTenantKey()),
            ],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('products', 'id')
                    ->where('tenant_id', (string) app('currentTenant')->getTenantKey())
                    ->where('engine_code', 'sales'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $order = $orders->create((string) app('currentTenant')->getTenantKey(), [
            ...$validated,
            'branch_id' => $validated['branch_id'] ?? app('currentBranch')?->getKey(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan penjualan berhasil dibuat.',
            'data' => $order,
        ], 201);
    }

    public function show(SalesOrder $salesOrder, ManageSalesOrders $orders): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $orders->detail($salesOrder),
        ]);
    }

    public function update(Request $request, SalesOrder $salesOrder, ManageSalesOrders $orders): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['draft', 'pending', 'completed', 'cancelled'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan penjualan berhasil diperbarui.',
            'data' => $orders->update($salesOrder, $validated),
        ]);
    }
}
