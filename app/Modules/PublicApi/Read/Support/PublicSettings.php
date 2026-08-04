<?php

namespace App\Modules\PublicApi\Read\Support;

use App\Models\TenantSetting;

final class PublicSettings
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $settings = null;

    public function value(string $group, string $key, mixed $default = null): mixed
    {
        return $this->all()[$group][$key] ?? $default;
    }

    public function string(
        string $group,
        string $key,
        ?string $default = null,
        int $maximumLength = 500,
    ): ?string {
        $value = $this->value($group, $key, $default);

        if (! is_string($value)) {
            return $default;
        }

        $value = trim(strip_tags($value));

        if ($value === '') {
            return $default;
        }

        return mb_substr($value, 0, $maximumLength);
    }

    public function url(string $group, string $key): ?string
    {
        $value = $this->string($group, $key, maximumLength: 2048);

        if ($value === null) {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $value : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $this->settings = TenantSetting::query()
            ->whereIn('group', ['regular', 'branding', 'public', 'homepage'])
            ->get(['group', 'key', 'value'])
            ->groupBy('group')
            ->map(fn ($items): array => $items
                ->mapWithKeys(fn (TenantSetting $setting): array => [
                    $setting->key => $setting->value,
                ])
                ->all())
            ->all();

        return $this->settings;
    }
}
