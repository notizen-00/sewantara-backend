<?php

namespace App\Modules\PublicApi\Read;

use App\Models\PublicArticle;
use App\Models\RentalConfiguration;
use App\Models\Tenant;
use App\Models\TenantBusinessProfile;
use App\Models\TenantPaymentMethod;
use App\Modules\PublicApi\Read\Support\PublicBranchResolver;
use App\Modules\PublicApi\Read\Support\PublicMediaUrl;
use App\Modules\PublicApi\Read\Support\PublicSettings;

final class PublicTenantProfileService
{
    public function __construct(
        private readonly PublicBranchResolver $branches,
        private readonly PublicSettings $settings,
        private readonly PublicMediaUrl $media,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(Tenant $tenant): array
    {
        $profile = TenantBusinessProfile::query()->first();
        $branch = $this->branches->resolve();
        $onlineBookingEnabled = RentalConfiguration::query()->where('allow_online_booking', true)->exists();
        $methods = TenantPaymentMethod::query()
            ->where('is_enabled', true)
            ->orderBy('method')
            ->get(['method', 'is_enabled']);
        $logoPath = $this->settings->value('branding', 'logo_path');
        $faviconPath = $this->settings->value('branding', 'favicon_path');
        $primaryColor = $this->color(
            $this->settings->value('branding', 'primary_color'),
            '#2563EB',
        );
        $secondaryColor = $this->color(
            $this->settings->value('branding', 'secondary_color'),
            '#111827',
        );
        $gatewayMethods = array_keys((array) config('payments.drivers', []));
        $enabledMethods = $methods->pluck('method')->all();

        return [
            'id' => $tenant->public_id,
            'slug' => $tenant->slug,
            'name' => $profile?->business_name ?: $tenant->name,
            'status' => 'active',
            'tagline' => $this->settings->string('public', 'tagline'),
            'description' => $this->settings->string(
                'public',
                'description',
                maximumLength: 3000,
            ),
            'logo_url' => $this->media->branding(
                'logo',
                is_string($logoPath) ? $logoPath : null,
            ),
            'favicon_url' => $this->media->branding(
                'favicon',
                is_string($faviconPath) ? $faviconPath : null,
            ),
            'theme' => [
                'primary' => $primaryColor,
                'secondary' => $secondaryColor,
                'font' => $this->settings->string(
                    'branding',
                    'font',
                    'Inter',
                    100,
                ),
            ],
            'contact' => [
                'phone' => $this->settings->string(
                    'public',
                    'phone',
                    $this->settings->string(
                        'public',
                        'contact_phone',
                        $branch?->phone,
                        30,
                    ),
                    30,
                ),
                'whatsapp' => $this->settings->string(
                    'public',
                    'whatsapp',
                    $this->settings->string(
                        'public',
                        'contact_whatsapp',
                        maximumLength: 30,
                    ),
                    maximumLength: 30,
                ),
                'email' => $this->email(
                    $this->settings->value(
                        'public',
                        'email',
                        $this->settings->value(
                            'public',
                            'contact_email',
                            $branch?->email,
                        ),
                    ),
                ),
            ],
            'location' => $branch === null ? null : [
                'name' => $branch->name,
                'address' => $branch->address,
                'latitude' => $branch->latitude === null
                    ? null
                    : (float) $branch->latitude,
                'longitude' => $branch->longitude === null
                    ? null
                    : (float) $branch->longitude,
            ],
            'operating_hours' => $profile?->operating_hours ?? [],
            'social_media' => $this->socialMedia(),
            'payment_methods' => $methods
                ->map(fn (TenantPaymentMethod $method): array => [
                    'code' => $method->method,
                    'label' => $this->paymentLabel($method->method),
                    'online' => in_array($method->method, $gatewayMethods, true),
                ])
                ->values()
                ->all(),
            'timezone' => $profile?->timezone ?: $tenant->timezone,
            'locale' => $tenant->locale,
            'currency' => $profile?->currency ?: $tenant->currency,
            'features' => [
                'guest_checkout' => $this->featureSetting(
                    'guest_checkout',
                    true,
                ),
                'online_booking' => $onlineBookingEnabled,
                'online_payment' => $this->featureSetting('online_payment', true)
                    && array_intersect($enabledMethods, $gatewayMethods) !== [],
                'blog' => $this->featureSetting('blog', true)
                    && PublicArticle::query()->published()->exists(),
                'reviews' => $this->featureSetting('reviews', false),
            ],
            'seo' => [
                'title' => $this->settings->string(
                    'public',
                    'seo_title',
                    $profile?->business_name ?: $tenant->name,
                    200,
                ),
                'description' => $this->settings->string(
                    'public',
                    'seo_description',
                    maximumLength: 500,
                ),
            ],
        ];
    }

    /** @return array<string, string> */
    private function socialMedia(): array
    {
        $allowed = ['facebook', 'instagram', 'linkedin', 'tiktok', 'x', 'youtube'];
        $configured = $this->settings->value('public', 'social_media', []);
        $result = [];

        foreach ($allowed as $network) {
            $value = is_array($configured) ? ($configured[$network] ?? null) : null;

            if (! is_string($value)) {
                $value = $this->settings->value('public', 'social_'.$network);
            }

            if (! is_string($value)) {
                continue;
            }

            $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

            if (in_array($scheme, ['http', 'https'], true)) {
                $result[$network] = mb_substr(trim($value), 0, 2048);
            }
        }

        return $result;
    }

    private function color(mixed $value, string $default): string
    {
        return is_string($value)
            && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
                ? strtoupper($value)
                : $default;
    }

    private function email(mixed $value): ?string
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)
            ? mb_substr(strtolower(trim($value)), 0, 150)
            : null;
    }

    private function featureSetting(string $key, bool $default): bool
    {
        $features = $this->settings->value('public', 'features', []);

        if (is_array($features) && is_bool($features[$key] ?? null)) {
            return $features[$key];
        }

        $value = $this->settings->value('public', $key, $default);

        return is_bool($value) ? $value : $default;
    }

    private function paymentLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Tunai',
            'transfer' => 'Transfer bank',
            'deposit' => 'Deposit',
            'pay_later' => 'Bayar nanti',
            'midtrans' => 'Pembayaran online',
            default => ucfirst(str_replace(['-', '_'], ' ', $method)),
        };
    }
}
