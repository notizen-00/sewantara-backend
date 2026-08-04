<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Support\PublicApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfrastructureHealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return PublicApiResponse::success($request, [
            'status' => 'ok',
        ])->withHeaders(['Cache-Control' => 'no-store, private']);
    }
}
