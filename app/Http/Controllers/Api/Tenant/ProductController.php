<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Models\Product;
use App\Modules\Inventory\Application\ManageProducts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(
        Request $request,
        ManageProducts $products,
        string $tenant,
    ): JsonResponse {
        $result = $products->paginate(
            search: $request->string('search')->toString() ?: null,
            categoryId: $request->integer('category_id') ?: null,
            inventoryType: $request->string('inventory_type')->toString() ?: null,
            isActive: $request->has('is_active')
                ? $request->boolean('is_active')
                : null,
            perPage: $request->integer('per_page', 20),
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function store(
        StoreProductRequest $request,
        ManageProducts $products,
        string $tenant,
    ): JsonResponse {
        $product = $products->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dibuat.',
            'data' => $product,
        ], 201);
    }

    public function show(
        string $tenant,
        ManageProducts $products,
        Product $product,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $products->detail($product),
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        string $tenant,
        ManageProducts $products,
        Product $product,
    ): JsonResponse {
        $product = $products->update($product, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui.',
            'data' => $product,
        ]);
    }

    public function destroy(
        string $tenant,
        ManageProducts $products,
        Product $product,
    ): JsonResponse {
        $products->delete($product);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus.',
            'data' => null,
        ]);
    }
}
