<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Application\ManageInventoryStocks;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function stocks(Request $request, ManageInventoryStocks $inventory)
    {
        return response()->json([
            'success' => true,
            'data' => $inventory->stockMovements(
                $request->integer('product_id') ?: null,
                $request->integer('branch_id') ?: null,
                $request->integer('per_page', 20),
            ),
        ]);
    }

    public function units(Request $request, ManageInventoryStocks $inventory)
    {
        return response()->json([
            'success' => true,
            'data' => $inventory->unitMovements(
                $request->integer('product_unit_id') ?: null,
                $request->integer('per_page', 20),
            ),
        ]);
    }
}
