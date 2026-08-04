<?php

namespace App\Modules\PublicApi\Application;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\TenantBusinessProfile;
use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class GetPublicTenantProfile
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $profile = TenantBusinessProfile::query()->first();

        $settings = TenantSetting::query()
            ->whereIn('group', [
                'regular',
                'branding',
                'public',
            ])
            ->get()
            ->groupBy('group')
            ->map(
                fn($items): array => $items
                    ->mapWithKeys(
                        fn(TenantSetting $setting): array => [
                            $setting->key => $setting->value,
                        ],
                    )
                    ->all(),
            )
            ->all();

        $regular = Arr::get($settings, 'regular', []);
        $branding = Arr::get($settings, 'branding', []);
        $public = Arr::get($settings, 'public', []);

        $features = is_array($public['features'] ?? null)
            ? $public['features']
            : [];

        $socialMedia = is_array($public['social_media'] ?? null)
            ? $public['social_media']
            : [];

        $hostname = (string) $request->attributes->get('tenant_host');

        $businessName = (string) (
            $profile?->business_name
            ?: $tenant->name
        );

        $description = (string) ($public['description'] ?? '');

        $primaryColor = (string) (
            $branding['primary_color']
            ?? '#C62828'
        );

        $secondaryColor = (string) (
            $branding['secondary_color']
            ?? '#111111'
        );

        return [
            'id' => (string) (
                $tenant->public_id
                ?: $tenant->getTenantKey()
            ),

            'slug' => (string) $tenant->slug,

            'hostname' => $hostname,

            'status' => $this->publicStatus($tenant),

            'businessName' => $businessName,

            'legalName' => null,

            'tagline' => (string) (
                $public['tagline']
                ?? ''
            ),

            'description' => $description,

            'timezone' => (string) (
                $profile?->timezone
                ?: $tenant->timezone
                ?: config(
                    'public-api.defaults.timezone',
                    'Asia/Jakarta',
                )
            ),

            'locale' => (string) (
                $tenant->locale
                ?: (
                    $regular['default_language']
                    ?? config(
                        'public-api.defaults.locale',
                        'id-ID',
                    )
                )
            ),

            'currency' => (string) (
                $profile?->currency
                ?: $tenant->currency
                ?: config(
                    'public-api.defaults.currency',
                    'IDR',
                )
            ),

            'theme' => [
                'primary' => $primaryColor,
                'primaryForeground' => '#FFFFFF',

                'secondary' => $secondaryColor,
                'secondaryForeground' => '#FFFFFF',

                'accent' => $primaryColor,
                'background' => '#FFFFFF',
                'foreground' => '#111111',
                'muted' => '#F5F5F5',

                'fontFamily' => $this->fontFamily(
                    $branding['font'] ?? null,
                ),

                'logo' => [
                    'url' => '',
                    'alt' => "Logo {$businessName}",
                ],

                'favicon' => '',

                'darkMode' => false,
            ],

            'contact' => [
                'phone' => (string) (
                    $public['phone']
                    ?? $tenant->phone
                    ?? ''
                ),

                'whatsapp' => (string) (
                    $public['whatsapp']
                    ?? ''
                ),

                'email' => (string) (
                    $public['email']
                    ?? $tenant->email
                    ?? ''
                ),

                'address' => (string) (
                    $profile?->address
                    ?? $tenant->address
                    ?? ''
                ),

                'instagram' => (string) (
                    $socialMedia['instagram']
                    ?? ''
                ),

                'facebook' => (string) (
                    $socialMedia['facebook']
                    ?? ''
                ),

                'tiktok' => (string) (
                    $socialMedia['tiktok']
                    ?? ''
                ),
            ],

            'businessHours' => $this->businessHours(
                $profile?->operating_hours,
            ),

            'locations' => $this->locations(),

            'paymentMethods' => [],

            'features' => [
                'customerLogin' => false,

                'guestBooking' => (bool) (
                    $features['guest_checkout']
                    ?? true
                ),

                'wishlist' => false,

                'reviews' => (bool) (
                    $features['reviews']
                    ?? true
                ),

                'blog' => (bool) (
                    $features['blog']
                    ?? true
                ),

                'darkMode' => false,
            ],

            'seo' => [
                'title' => (string) (
                    $public['seo_title']
                    ?? $businessName
                ),

                'titleTemplate' => "%s | {$businessName}",

                'description' => (string) (
                    $public['seo_description']
                    ?? $description
                ),

                'canonicalUrl' => $hostname !== ''
                    ? "https://{$hostname}"
                    : '',

                'ogImage' => '',

                'keywords' => [],
            ],

            'termsUrl' => '/terms',

            'privacyUrl' => '/privacy',

            'cancellationPolicyUrl' => '/cancellation-policy',

            'configVersion' => optional(
                $tenant->updated_at,
            )->toIso8601String() ?? '1',
        ];
    }

    private function publicStatus(Tenant $tenant): string
    {
        return match ((string) $tenant->status) {
            'maintenance' => 'maintenance',

            'suspended',
            'disabled' => 'suspended',

            default => 'active',
        };
    }

    private function fontFamily(mixed $font): string
    {
        return in_array(
            $font,
            ['Inter', 'Geist', 'system'],
            true,
        )
            ? $font
            : 'Inter';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function locations(): array
    {
        return Branch::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(
                fn(Branch $branch): array => [
                    'id' => (string) $branch->getKey(),

                    'name' => (string) $branch->name,

                    'address' => (string) (
                        $branch->address
                        ?? ''
                    ),

                    'city' => '',

                    'latitude' => $branch->latitude !== null
                        ? (float) $branch->latitude
                        : null,

                    'longitude' => $branch->longitude !== null
                        ? (float) $branch->longitude
                        : null,

                    'isPrimary' => (bool) (
                        $branch->is_primary
                        ?? false
                    ),
                ],
            )
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function businessHours(mixed $hours): array
    {
        if (! is_array($hours)) {
            return [];
        }

        return collect($hours)
            ->map(function (
                mixed $value,
                mixed $day,
            ): array {
                $schedule = is_array($value)
                    ? $value
                    : [];

                $open = $schedule['open'] ?? null;
                $close = $schedule['close'] ?? null;

                return [
                    'day' => (string) $day,

                    'open' => is_string($open)
                        ? $open
                        : null,

                    'close' => is_string($close)
                        ? $close
                        : null,

                    'label' => is_string($open)
                        && is_string($close)
                        ? "{$open} - {$close}"
                        : 'Tutup',
                ];
            })
            ->values()
            ->all();
    }
}
