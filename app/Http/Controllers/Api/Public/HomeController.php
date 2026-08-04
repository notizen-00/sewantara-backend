<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Resources\Public\Home\HomeResource;
use App\Modules\PublicApi\Application\Home\GetPublicHome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends PublicReadController
{
    public function __invoke(
        Request $request,
        GetPublicHome $home,
    ): JsonResponse {
        $resource = new HomeResource(
            $home->execute($request),
        );

        return $this->cached(
            $request,
            $resource->resolve($request),
        );
    }
}
