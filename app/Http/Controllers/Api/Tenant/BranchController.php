<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Modules\Organization\Application\ManageBranches;
use App\Modules\Organization\Application\SyncBranchMasterData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(ManageBranches $branches)
    {
        return response()->json(['success' => true, 'data' => $branches->paginate()]);
    }

    public function store(Request $request, ManageBranches $branches)
    {
        $tenantId = app('currentTenant')->id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', Rule::unique('branches', 'code')->where('tenant_id', $tenantId)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cabang berhasil dibuat.',
            'data' => $branches->create($validated, $request->user()?->id),
        ], 201);
    }

    public function syncMasterData(
        Request $request,
        Branch $branch,
        SyncBranchMasterData $sync,
    ): JsonResponse {
        abort_unless(
            $request->user()
                ->branches()
                ->where('branches.id', $branch->getKey())
                ->exists(),
            403,
            'Anda tidak memiliki akses ke cabang tujuan.',
        );

        $validated = $request->validate([
            'sync_prices' => ['nullable', 'boolean'],
            'prepare_stocks' => ['nullable', 'boolean'],
            'overwrite_prices' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Master data antar cabang berhasil disinkronkan.',
            'data' => $sync->execute(
                app('currentBranch'),
                $branch,
                $validated['sync_prices'] ?? true,
                $validated['prepare_stocks'] ?? true,
                $validated['overwrite_prices'] ?? false,
            ),
        ]);
    }
}
