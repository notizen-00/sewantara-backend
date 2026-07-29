<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Inventory\Application\ManageProducts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request, ManageProducts $products)
    {
        $result = $products->paginate(
            $request->string('search')->toString() ?: null,
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function store(Request $request, ManageProducts $products)
    {
        $tenantId = app('currentTenant')->id;
        $validated = $request->validate([
            'category_id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->where('tenant_id', $tenantId)],
            'inventory_type' => ['required', Rule::in(['serialized', 'quantity'])],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $product = $products->create($validated);

        return response()->json(['success' => true, 'message' => 'Produk berhasil dibuat.', 'data' => $product], 201);
    }

    public function show(Product $product, ManageProducts $products)
    {
        return response()->json(['success' => true, 'data' => $products->detail($product)]);
    }

    public function update(Request $request, Product $product, ManageProducts $products)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'deposit_amount' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product = $products->update($product, $validated);

        return response()->json(['success' => true, 'message' => 'Produk berhasil diperbarui.', 'data' => $product]);
    }
}
