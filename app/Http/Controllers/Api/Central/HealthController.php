<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Sewantara API is running.',
        ]);
    }
}
