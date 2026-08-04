<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPublicTenantLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $previous = app()->getLocale();
        $tenant = $request->attributes->get('tenant');

        if ($tenant instanceof Tenant) {
            $requested = strtolower(str_replace('_', '-', (string) $tenant->locale));
            $locales = config('public-api.translation_locales', []);
            $locale = is_array($locales)
                ? ($locales[$requested] ?? null)
                : null;

            app()->setLocale(is_string($locale) && $locale !== ''
                ? $locale
                : (string) config('app.locale', 'id'));
        }

        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
        }
    }
}
