<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ProductPrice;
use App\Modules\Inventory\Application\ManageProductPrices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductPriceController extends Controller
{
    public function index(
        Request $request,
        ManageProductPrices $prices,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $prices->paginate(
                $request->integer('product_id') ?: null,
                app('currentBranch')->getKey(),
                $request->integer('per_page', 20),
            ),
        ]);
    }

    public function store(
        Request $request,
        ManageProductPrices $prices,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Harga produk berhasil dibuat.',
            'data' => $prices->create([
                ...$this->validated($request),
                'branch_id' => app('currentBranch')->getKey(),
            ]),
        ], 201);
    }

    public function update(
        Request $request,
        ProductPrice $productPrice,
        ManageProductPrices $prices,
    ): JsonResponse {
        $this->ensureCurrentBranch($productPrice);

        return response()->json([
            'success' => true,
            'message' => 'Harga produk berhasil diperbarui.',
            'data' => $prices->update(
                $productPrice,
                $this->validated($request, true),
            ),
        ]);
    }

    public function destroy(
        ProductPrice $productPrice,
        ManageProductPrices $prices,
    ): JsonResponse {
        $this->ensureCurrentBranch($productPrice);

        $prices->delete($productPrice);

        return response()->json([
            'success' => true,
            'message' => 'Harga produk berhasil dihapus.',
            'data' => null,
        ]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'product_id' => [$presence, 'integer', 'min:1'],
            'branch_id' => ['prohibited'],
            'pricing_type' => [
                $presence,
                Rule::in(['hourly', 'daily', 'weekly', 'monthly', 'event', 'custom']),
            ],
            'duration' => [$presence, 'integer', 'min:1'],
            'price' => [$presence, 'numeric', 'min:0'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function ensureCurrentBranch(ProductPrice $productPrice): void
    {
        abort_unless(
            (int) $productPrice->branch_id === (int) app('currentBranch')->getKey(),
            404,
        );
    }
}
