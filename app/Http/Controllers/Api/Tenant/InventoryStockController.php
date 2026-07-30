<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Application\ManageInventoryStocks;
use Illuminate\Http\Request;

class InventoryStockController extends Controller
{
    public function index(Request $request, ManageInventoryStocks $stocks)
    {
        return response()->json([
            'success' => true,
            'data' => $stocks->paginate(
                $request->integer('product_id') ?: null,
                app('currentBranch')->getKey(),
                $request->integer('per_page', 20),
            ),
        ]);
    }

    public function adjust(Request $request, ManageInventoryStocks $stocks)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['prohibited'],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stok berhasil disesuaikan.',
            'data' => $stocks->adjust(
                app('currentTenant')->id,
                $request->user()?->id,
                [
                    ...$validated,
                    'branch_id' => app('currentBranch')->getKey(),
                ],
            ),
        ]);
    }
}
