<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Modules\MembershipEngine\Application\ManageMemberships;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipController extends Controller
{
    public function index(Request $request, ManageMemberships $memberships): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $memberships->paginate(
                $request->string('status')->toString() ?: null,
                $request->integer('per_page', 20),
            ),
        ]);
    }

    public function store(Request $request, ManageMemberships $memberships): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('products', 'id')
                    ->where('tenant_id', (string) app('currentTenant')->getTenantKey())
                    ->where('engine_code', 'membership'),
            ],
            'customer_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('customers', 'id')
                    ->where('tenant_id', (string) app('currentTenant')->getTenantKey()),
            ],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'price_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $membership = $memberships->create([
            ...$validated,
            'branch_id' => $validated['branch_id'] ?? app('currentBranch')?->getKey(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Membership berhasil dibuat.',
            'data' => $membership,
        ], 201);
    }

    public function show(Membership $membership, ManageMemberships $memberships): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $memberships->detail($membership),
        ]);
    }

    public function update(Request $request, Membership $membership, ManageMemberships $memberships): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'active', 'frozen', 'expired', 'renewed', 'cancelled'])],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_on'],
            'price_amount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Membership berhasil diperbarui.',
            'data' => $memberships->update($membership, $validated),
        ]);
    }
}
