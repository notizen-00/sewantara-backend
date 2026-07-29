<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Internal API placeholder is ready.',
        ]);
    }
}
