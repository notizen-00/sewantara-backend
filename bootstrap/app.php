<?php

use App\Http\Middleware\ApplyPublicResponseHeaders;
use App\Http\Middleware\AssignPublicRequestId;
use App\Http\Middleware\AuthenticateBffService;
use App\Http\Middleware\AuthenticateInternalService;
use App\Http\Middleware\EnforcePublicTenantRateLimit;
use App\Http\Middleware\EnsureTenantEngineEnabled;
use App\Http\Middleware\EnsureTenantIsAccessible;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureTenantSubscriptionActive;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\ForcePublicJsonResponse;
use App\Http\Middleware\InitializePublicTenancy;
use App\Http\Middleware\InitializeTenantByPath;
use App\Http\Middleware\ObservePublicApiRequest;
use App\Http\Middleware\ResolveBranchContext;
use App\Http\Middleware\ResolvePublicTenant;
use App\Http\Middleware\SetPublicTenantLocale;
use App\Http\Middleware\TrustConfiguredProxies;
use App\Http\Middleware\ValidatePublicTenantEligibility;
use App\Http\Middleware\ValidatePublicTenantHeaders;
use App\Modules\SubscriptionBilling\Application\Exceptions\SubscriptionGatewayAuthenticationFailed;
use App\Modules\TenantOnboarding\Application\Exceptions\BillingIntervalUnavailable;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use App\Support\PublicApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies as FrameworkTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')->group(base_path('routes/public-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(static function (): array {
            $hosts = config('public-api.trusted_hosts', []);

            if (! is_array($hosts)) {
                return [];
            }

            return array_values(array_filter(array_map(
                static function (mixed $host): ?string {
                    if (! is_string($host) || trim($host) === '') {
                        return null;
                    }

                    $candidate = str_contains($host, '://')
                        ? $host
                        : 'http://'.trim($host);
                    $normalized = parse_url($candidate, PHP_URL_HOST);

                    return is_string($normalized) && $normalized !== ''
                        ? '^'.preg_quote(strtolower($normalized), '{').'$'
                        : null;
                },
                $hosts,
            )));
        }, false);
        $middleware->replace(
            FrameworkTrustProxies::class,
            TrustConfiguredProxies::class,
        );
        $middleware->append([
            ObservePublicApiRequest::class,
            ApplyPublicResponseHeaders::class,
        ]);

        $middleware->alias([
            'request.id' => AssignPublicRequestId::class,
            'force.json' => ForcePublicJsonResponse::class,
            'bff.auth' => AuthenticateBffService::class,
            'internal.auth' => AuthenticateInternalService::class,
            'public.tenant.headers' => ValidatePublicTenantHeaders::class,
            'public.tenant.resolve' => ResolvePublicTenant::class,
            'public.tenant.eligible' => ValidatePublicTenantEligibility::class,
            'public.tenant.initialize' => InitializePublicTenancy::class,
            'public.tenant.locale' => SetPublicTenantLocale::class,
            'public.tenant.rate' => EnforcePublicTenantRateLimit::class,
            'tenant.path' => InitializeTenantByPath::class,
            'tenant.branch' => ResolveBranchContext::class,
            'tenant.user' => EnsureUserBelongsToTenant::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'tenant.accessible' => EnsureTenantIsAccessible::class,
            'tenant.subscription' => EnsureTenantSubscriptionActive::class,
            'tenant.engine' => EnsureTenantEngineEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isPublicRequest = static fn (Request $request): bool => $request->is('v1/public')
            || $request->is('v1/public/*')
            || $request->is('healthz')
            || $request->is('readyz');

        $exceptions->render(function (
            ValidationException $exception,
            Request $request,
        ) use ($isPublicRequest) {
            if (! $isPublicRequest($request)) {
                return null;
            }

            return PublicApiResponse::error(
                $request,
                'VALIDATION_ERROR',
                'Data yang dikirim tidak valid.',
                $exception->status,
                $exception->errors(),
            );
        });

        $exceptions->render(function (
            PublicApiException $exception,
            Request $request,
        ) use ($isPublicRequest) {
            if (! $isPublicRequest($request)) {
                return null;
            }

            return PublicApiResponse::error(
                $request,
                $exception->errorCode,
                $exception->getMessage(),
                $exception->httpStatus,
                $exception->fields,
            );
        });

        $exceptions->render(
            fn (SubscriptionGatewayAuthenticationFailed $exception, Request $request) => response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_GATEWAY_AUTH_FAILED',
                    'message' => 'Konfigurasi autentikasi payment gateway tidak valid. Hubungi administrator.',
                    'details' => null,
                ],
            ], 502),
        );

        $exceptions->render(function (
            AuthenticationException $exception,
            Request $request,
        ) use ($isPublicRequest) {
            if ($isPublicRequest($request)) {
                return PublicApiResponse::error(
                    $request,
                    'AUTH_SERVICE_REQUIRED',
                    'Kredensial layanan diperlukan.',
                    401,
                );
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Bearer token diperlukan.',
                    'details' => null,
                ],
            ], 401);
        });

        $exceptions->render(
            fn (BillingIntervalUnavailable $exception, Request $request) => response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BILLING_INTERVAL_UNAVAILABLE',
                    'message' => $exception->getMessage(),
                    'details' => [
                        'billing_interval' => [$exception->getMessage()],
                    ],
                ],
            ], 422),
        );

        $exceptions->render(function (
            TenantCouldNotBeIdentifiedOnDomainException $exception,
            Request $request,
        ) use ($isPublicRequest) {
            if ($isPublicRequest($request)) {
                return PublicApiResponse::error(
                    $request,
                    'TENANT_NOT_FOUND',
                    'Tenant tidak ditemukan.',
                    404,
                );
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DOMAIN_NOT_FOUND',
                    'message' => 'Domain ini tidak terdaftar.',
                    'details' => null,
                ],
            ], 404);
        });

        $exceptions->render(function (
            HttpExceptionInterface $exception,
            Request $request,
        ) use ($isPublicRequest) {
            if (! $isPublicRequest($request)) {
                return null;
            }

            $status = $exception->getStatusCode();
            [$code, $message] = match (true) {
                $status === 404 => [
                    'RESOURCE_NOT_FOUND',
                    'Resource tidak ditemukan.',
                ],
                $status === 429 => [
                    'RATE_LIMITED',
                    'Terlalu banyak permintaan.',
                ],
                $status >= 500 => [
                    'INTERNAL_ERROR',
                    'Terjadi kesalahan pada layanan.',
                ],
                default => [
                    'REQUEST_INVALID',
                    'Permintaan tidak valid.',
                ],
            };
            $response = PublicApiResponse::error(
                $request,
                $code,
                $message,
                $status,
            );
            $response->headers->add($exception->getHeaders());

            return $response;
        });

        $exceptions->render(function (
            Throwable $exception,
            Request $request,
        ) use ($isPublicRequest) {
            if (! $isPublicRequest($request)) {
                return null;
            }

            return PublicApiResponse::error(
                $request,
                'INTERNAL_ERROR',
                'Terjadi kesalahan pada layanan.',
                500,
            );
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $isPublicRequest($request),
        );

        $exceptions->respond(function (
            Response $response,
            Throwable $exception,
            Request $request,
        ) use ($isPublicRequest): Response {
            if ($isPublicRequest($request)) {
                $response->headers->set(
                    'X-Request-Id',
                    PublicApiResponse::requestId($request),
                );
                $response->headers->set('X-Content-Type-Options', 'nosniff');

                if ($request->is('v1/public')
                    || $request->is('v1/public/*')) {
                    ApplyPublicResponseHeaders::applyTenantVary($response);
                    $response->headers->set(
                        'Cache-Control',
                        'private, no-store',
                    );
                }
            }

            return $response;
        });
    })->create();
