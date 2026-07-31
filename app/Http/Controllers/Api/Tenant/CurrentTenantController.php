<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\SubscriptionCatalog\Application\GetCurrentTenantSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentTenantController extends Controller
{
    public function __invoke(
        Request $request,
        GetCurrentTenantSubscription $currentSubscription,
    ): JsonResponse {
        $branchId = $request->attributes->get('branch')?->getKey();
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        $user?->load([
            'roles' => fn ($query) => $query
                ->wherePivotNull('branch_id')
                ->orWherePivot('branch_id', $branchId)
                ->orderByDesc('is_system')
                ->orderBy('code')
                ->with(['permissions' => fn ($query) => $query->orderBy('module')->orderBy('code')]),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
                'branch' => $request->attributes->get('branch'),
                'user' => $user,
                'subscription' => $currentSubscription->execute($tenant),
            ],
        ]);
    }
}
