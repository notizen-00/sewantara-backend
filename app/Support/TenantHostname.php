<?php

namespace App\Support;

final class TenantHostname
{
    public static function fromSubdomain(string $subdomain): string
    {
        return sprintf(
            '%s.%s',
            strtolower(trim($subdomain)),
            strtolower(trim((string) config('tenancy.tenant_base_domain'), '.')),
        );
    }

    public static function url(string $hostname): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME)
            ?: (app()->isProduction() ? 'https' : 'http');

        return sprintf('%s://%s', $scheme, $hostname);
    }
}
