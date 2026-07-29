<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Modules\SubscriptionCatalog\Application\ListActivePlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __invoke(Request $request, ListActivePlans $listActivePlans): JsonResponse
    {
        $plans = $listActivePlans->execute(
            $request->string('billing_interval')->toString() ?: null,
            $request->string('currency')->toString() ?: null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Daftar paket berhasil diambil.',
            'data' => $plans,
            'meta' => null,
        ]);
    }
}
