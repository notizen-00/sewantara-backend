<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Modules\TenantOnboarding\Application\ListBusinessTemplates;
use Illuminate\Http\JsonResponse;

class BusinessTemplateController extends Controller
{
    public function index(ListBusinessTemplates $templates): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $templates->execute(),
        ]);
    }
}
