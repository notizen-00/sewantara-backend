<?php

$apiHost = env(
    'API_DOMAIN',
    parse_url((string) env('APP_URL', ''), PHP_URL_HOST),
);
$additionalTrustedHosts = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_HOSTS', '')),
)));

return [
    'api_host' => $apiHost,
    'trusted_hosts' => array_values(array_unique(array_filter([
        $apiHost,
        ...$additionalTrustedHosts,
    ], static fn (mixed $host): bool => is_string($host) && $host !== ''))),
    'trusted_proxy_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXY_IPS', '')),
    ))),
    'enabled' => (bool) env('TENANT_PUBLIC_WEB_ENABLED', true),

    'bff_tokens' => array_filter([
        'current' => env('BFF_SERVICE_TOKEN_CURRENT'),
        'previous' => env('BFF_SERVICE_TOKEN_PREVIOUS'),
    ], static fn (mixed $token): bool => is_string($token) && $token !== ''),

    'trusted_bff_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_BFF_IPS', '')),
    ))),

    'internal_health_token' => env('INTERNAL_HEALTH_TOKEN'),
    'readiness_cache_store' => env('READINESS_CACHE_STORE', env('CACHE_STORE')),
    'resolution_cache_store' => env(
        'TENANT_RESOLUTION_CACHE_STORE',
        env('CACHE_STORE'),
    ),
    'content_cache_store' => env(
        'TENANT_PUBLIC_CONTENT_CACHE_STORE',
        env('CACHE_STORE'),
    ),
    'idempotency_cache_store' => env(
        'PUBLIC_IDEMPOTENCY_CACHE_STORE',
        env('CACHE_STORE'),
    ),
    // Browser-facing URL handled by the Nuxt BFF; never put the BFF token here.
    'public_media_base_url' => env(
        'TENANT_PUBLIC_MEDIA_BASE_URL',
        '/api/public',
    ),

    'tenant_base_domain' => env(
        'CENTRAL_DOMAIN',
        env('TENANT_BASE_DOMAIN', 'localhost'),
    ),

    'reserved_slugs' => [
        'www',
        'api',
        'app',
        'admin',
        'dashboard',
        'auth',
        'login',
        'register',
        'support',
        'help',
        'status',
        'static',
        'assets',
        'cdn',
        'mail',
        'email',
        'billing',
        'payment',
        'payments',
        'checkout',
        'webhook',
        'webhooks',
        'docs',
        'developer',
        'developers',
        'health',
        'healthz',
        'internal',
        'system',
        'root',
        'null',
        'undefined',
    ],

    'resolution_cache_ttl' => (int) env('TENANT_RESOLUTION_CACHE_TTL', 300),
    'content_cache_ttl' => (int) env('TENANT_PUBLIC_CONTENT_CACHE_TTL', 300),
    'quote_ttl_minutes' => (int) env('QUOTE_TTL_MINUTES', 15),
    'payment_ttl_minutes' => (int) env('PAYMENT_TTL_MINUTES', 30),
    'stock_hold_ttl_minutes' => (int) env('STOCK_HOLD_TTL_MINUTES', 20),
    'idempotency_ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),
    'expired_subscription_public_read' => (bool) env(
        'TENANT_EXPIRED_SUBSCRIPTION_PUBLIC_READ',
        false,
    ),
    'grace_period_public_read' => (bool) env(
        'TENANT_GRACE_PERIOD_PUBLIC_READ',
        true,
    ),

    'defaults' => [
        'timezone' => env('TENANT_DEFAULT_TIMEZONE', 'Asia/Jakarta'),
        'locale' => env('TENANT_DEFAULT_LOCALE', 'id-ID'),
        'currency' => env('TENANT_DEFAULT_CURRENCY', 'IDR'),
    ],

    'translation_locales' => [
        'id' => 'id',
        'id-id' => 'id',
    ],

    'response_cache' => [
        'max_age' => (int) env('TENANT_PUBLIC_CACHE_MAX_AGE', 60),
        'shared_max_age' => (int) env(
            'TENANT_PUBLIC_CACHE_SHARED_MAX_AGE',
            300,
        ),
        'stale_while_revalidate' => (int) env(
            'TENANT_PUBLIC_CACHE_STALE_WHILE_REVALIDATE',
            60,
        ),
        'cacheable_routes' => [
            'public.v1.tenant',
            'public.v1.home',
            'public.v1.categories.*',
            'public.v1.catalog.*',
            'public.v1.blog.*',
            'public.v1.sitemap',
        ],
    ],

    'rate_limits' => [
        'read' => ['max_attempts' => 120, 'decay_seconds' => 60],
        'product' => ['max_attempts' => 180, 'decay_seconds' => 60],
        'availability' => ['max_attempts' => 60, 'decay_seconds' => 60],
        'quote' => ['max_attempts' => 20, 'decay_seconds' => 60],
        'booking' => ['max_attempts' => 10, 'decay_seconds' => 600],
        'tracking' => ['max_attempts' => 10, 'decay_seconds' => 600],
        'payment' => ['max_attempts' => 30, 'decay_seconds' => 60],
    ],
];
