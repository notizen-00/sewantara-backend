<?php

namespace App\Http\Controllers\Api\Public;

use App\Modules\PublicApi\Application\GetPublicTenantProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends PublicReadController
{
    public function __invoke(
        Request $request,
        GetPublicTenantProfile $profile,
    ): JsonResponse {
        return $this->cached(
            $request,
            $profile->execute($request),
        );
    }
}
