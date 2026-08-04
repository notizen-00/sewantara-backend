<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustConfiguredProxies extends TrustProxies
{
    protected function proxies(): array|string|null
    {
        $proxies = config('public-api.trusted_proxy_ips', []);

        if (is_array($proxies) && $proxies !== []) {
            return $proxies;
        }

        return app()->environment('production')
            ? '*'
            : null;
    }

    protected function headers(): int
    {
        return Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO;
    }
}
