<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProductImageRequest;
use App\Http\Requests\Tenant\UpdateProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Modules\Inventory\Application\ManageProductImages;
use Illuminate\Http\JsonResponse;

class ProductImageController extends Controller
{
    public function store(
        StoreProductImageRequest $request,
        Product $product,
        ManageProductImages $images,
    ): JsonResponse {
        $image = $images->create(
            $product,
            $request->file('image'),
            $request->safe()->except('image'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Foto produk berhasil ditambahkan.',
            'data' => $image,
        ], 201);
    }

    public function update(
        UpdateProductImageRequest $request,
        Product $product,
        ProductImage $productImage,
        ManageProductImages $images,
    ): JsonResponse {
        $image = $images->update(
            $product,
            $productImage,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Foto produk berhasil diperbarui.',
            'data' => $image,
        ]);
    }

    public function destroy(
        Product $product,
        ProductImage $productImage,
        ManageProductImages $images,
    ): JsonResponse {
        $images->delete($product, $productImage);

        return response()->json([
            'success' => true,
            'message' => 'Foto produk berhasil dihapus.',
            'data' => null,
        ]);
    }
}
