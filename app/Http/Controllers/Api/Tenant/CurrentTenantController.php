<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentTenantController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $request->attributes->get('tenant'),
                'branch' => $request->attributes->get('branch'),
                'user' => $request->user(),
            ],
        ]);
    }
}
