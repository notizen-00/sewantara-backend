<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustConfiguredProxies extends TrustProxies
{
    protected function proxies()
    {
        $proxies = config('public-api.trusted_proxy_ips', []);

        return is_array($proxies) && $proxies !== [] ? $proxies : null;
    }

    protected function headers()
    {
        return Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO;
    }
}
