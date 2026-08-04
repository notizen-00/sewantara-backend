<?php

namespace App\Http\Middleware;

use App\Support\PublicApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateBffService
{
    public function handle(Request $request, Closure $next): Response
    {
        $trustedIps = config('public-api.trusted_bff_ips', []);

        if (is_array($trustedIps) && $trustedIps !== []) {
            try {
                $trusted = IpUtils::checkIp((string) $request->ip(), $trustedIps);
            } catch (Throwable) {
                $trusted = false;
            }

            if (! $trusted) {
                return $this->reject($request, 'AUTH_SERVICE_INVALID', 'ip');
            }
        }

        $provided = $request->bearerToken();

        if (! is_string($provided) || $provided === '') {
            return $this->reject($request, 'AUTH_SERVICE_REQUIRED', 'missing');
        }

        foreach (config('public-api.bff_tokens', []) as $slot => $token) {
            if (is_string($token) && hash_equals($token, $provided)) {
                $request->attributes->set(
                    'bff_service_id',
                    'tenant-web-'.(string) $slot,
                );

                return $next($request);
            }
        }

        return $this->reject($request, 'AUTH_SERVICE_INVALID', 'token');
    }

    private function reject(
        Request $request,
        string $code,
        string $reason,
    ): Response {
        Log::warning('BFF_AUTHENTICATION_FAILED', [
            'request_id' => PublicApiResponse::requestId($request),
            'client_ip' => $request->ip(),
            'reason' => $reason,
        ]);

        $response = PublicApiResponse::error(
            $request,
            $code,
            $code === 'AUTH_SERVICE_REQUIRED'
                ? 'Kredensial layanan diperlukan.'
                : 'Kredensial layanan tidak valid.',
            401,
        );
        $response->headers->set('WWW-Authenticate', 'Bearer');

        return $response;
    }
}
