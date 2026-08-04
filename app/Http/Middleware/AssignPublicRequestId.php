<?php

namespace App\Http\Middleware;

use App\Support\PublicApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssignPublicRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = PublicApiResponse::requestId($request);
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
