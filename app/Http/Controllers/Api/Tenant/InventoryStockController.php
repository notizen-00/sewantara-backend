<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AdjustInventoryStockRequest;
use App\Http\Requests\Tenant\TransferInventoryStockRequest;
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

    public function adjust(
        AdjustInventoryStockRequest $request,
        ManageInventoryStocks $stocks,
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Stok berhasil disesuaikan.',
            'data' => $stocks->adjust(
                app('currentTenant')->id,
                $request->user()?->id,
                [
                    ...$request->validated(),
                    'branch_id' => app('currentBranch')->getKey(),
                ],
            ),
        ]);
    }

    public function transfer(
        TransferInventoryStockRequest $request,
        ManageInventoryStocks $stocks,
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Stok berhasil dipindahkan antar cabang.',
            'data' => $stocks->transfer(
                app('currentTenant')->id,
                app('currentBranch')->getKey(),
                $request->user()?->id,
                $request->validated(),
            ),
        ], 201);
    }
}
