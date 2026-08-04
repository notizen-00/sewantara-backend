<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\ProductEngine\Application\ManageTenantEngines;
use App\Modules\ProductEngine\Domain\EngineCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EngineController extends Controller
{
    public function index(ManageTenantEngines $engines): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $engines->catalog($this->tenantId()),
        ]);
    }

    public function enable(Request $request, ManageTenantEngines $engines): JsonResponse
    {
        $validated = $request->validate([
            'engine_code' => ['required', Rule::enum(EngineCode::class)],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Engine berhasil diaktifkan.',
            'data' => $engines->enable($this->tenantId(), $validated['engine_code']),
        ]);
    }

    public function disable(Request $request, ManageTenantEngines $engines): JsonResponse
    {
        $validated = $request->validate([
            'engine_code' => ['required', Rule::enum(EngineCode::class)],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Engine berhasil dinonaktifkan.',
            'data' => $engines->disable($this->tenantId(), $validated['engine_code']),
        ]);
    }

    private function tenantId(): string
    {
        return (string) app('currentTenant')->getTenantKey();
    }
}
