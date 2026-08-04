<?php

namespace App\Http\Middleware;

use App\Support\PublicApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateInternalService
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('public-api.internal_health_token');
        $provided = $request->bearerToken();

        if (! is_string($provided) || $provided === '') {
            return $this->reject(
                $request,
                'AUTH_SERVICE_REQUIRED',
                'Kredensial layanan diperlukan.',
            );
        }

        if (! is_string($expected)
            || $expected === ''
            || ! hash_equals($expected, $provided)) {
            return $this->reject(
                $request,
                'AUTH_SERVICE_INVALID',
                'Kredensial layanan tidak valid.',
            );
        }

        $request->attributes->set('internal_service_id', 'readiness-probe');

        return $next($request);
    }

    private function reject(
        Request $request,
        string $code,
        string $message,
    ): Response {
        $response = PublicApiResponse::error(
            $request,
            $code,
            $message,
            401,
        );
        $response->headers->set('WWW-Authenticate', 'Bearer');

        return $response;
    }
}
