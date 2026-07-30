<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCategoryImageRequest;
use App\Http\Requests\Tenant\StoreCategoryRequest;
use App\Http\Requests\Tenant\UpdateCategoryRequest;
use App\Models\Category;
use App\Modules\Inventory\Application\ManageCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(
        Request $request,
        ManageCategories $categories,
    ): JsonResponse {
        $result = $categories->paginate(
            search: $request->string('search')->toString() ?: null,
            parentId: $request->integer('parent_id') ?: null,
            isActive: $request->has('is_active')
                ? $request->boolean('is_active')
                : null,
            rootsOnly: $request->boolean('roots_only'),
            perPage: $request->integer('per_page', 20),
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function store(
        StoreCategoryRequest $request,
        ManageCategories $categories,
    ): JsonResponse {
        $category = $categories->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kategori produk berhasil dibuat.',
            'data' => $category,
        ], 201);
    }

    public function show(
        ManageCategories $categories,
        Category $category,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $categories->detail($category),
        ]);
    }

    public function update(
        UpdateCategoryRequest $request,
        ManageCategories $categories,
        Category $category,
    ): JsonResponse {
        $category = $categories->update(
            $category,
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Kategori produk berhasil diperbarui.',
            'data' => $category,
        ]);
    }

    public function destroy(
        ManageCategories $categories,
        Category $category,
    ): JsonResponse {
        $categories->delete($category);

        return response()->json([
            'success' => true,
            'message' => 'Kategori produk berhasil dihapus.',
            'data' => null,
        ]);
    }

    public function storeImage(
        StoreCategoryImageRequest $request,
        ManageCategories $categories,
        Category $category,
    ): JsonResponse {
        $category = $categories->updateImage(
            $category,
            $request->file('image'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Gambar kategori berhasil diperbarui.',
            'data' => $category,
        ]);
    }

    public function destroyImage(
        ManageCategories $categories,
        Category $category,
    ): JsonResponse {
        $category = $categories->deleteImage($category);

        return response()->json([
            'success' => true,
            'message' => 'Gambar kategori berhasil dihapus.',
            'data' => $category,
        ]);
    }
}
