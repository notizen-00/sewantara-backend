<?php

namespace App\Modules\TenantSettings\Application;

use App\Models\Branch;
use App\Models\RentalConfiguration;
use App\Models\TenantBusinessProfile;
use App\Models\TenantSetting;
use App\Support\TenantPrivateMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class ManageTenantSettings
{
    public function __construct(
        private readonly TenantPrivateMedia $media,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $profile = TenantBusinessProfile::query()->first();
        $branch = app()->bound('currentBranch') ? app('currentBranch') : null;
        $branding = $this->settings('branding');

        return [
            'regular' => array_filter([
                'business_name' => $profile?->business_name,
                'timezone' => $profile?->timezone,
                'currency' => $profile?->currency,
                'operating_hours' => $profile?->operating_hours,
                ...$this->settings('regular'),
            ], static fn ($value): bool => $value !== null),
            'branding' => $this->appendMediaUrls($branding),
            'branch' => $this->branchPayload($branch),
            'rental_engine' => RentalConfiguration::query()->firstOrFail(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(array $attributes): array
    {
        DB::transaction(function () use ($attributes): void {
            $this->updateRegular($attributes['regular'] ?? []);
            $this->upsertSettings('branding', $attributes['branding'] ?? []);
            $this->updateBranch($attributes['branch'] ?? []);
            $this->updateRentalEngine($attributes['rental_engine'] ?? []);
        });

        return $this->payload();
    }

    /**
     * @param  array<string, UploadedFile>  $files
     * @return array<string, mixed>
     */
    public function updateImages(array $files): array
    {
        $definitions = $this->imageDefinitions();
        $storedPaths = [];
        $oldPaths = [];

        try {
            foreach ($definitions as $input => $definition) {
                if (! isset($files[$input])) {
                    continue;
                }

                $storedPaths[$input] = $this->media->store(
                    $files[$input],
                    $definition['directory'],
                );
            }

            DB::transaction(function () use ($definitions, $storedPaths, &$oldPaths): void {
                foreach ($storedPaths as $input => $path) {
                    $definition = $definitions[$input];
                    $setting = $this->setting($definition['group'], $definition['key']);
                    $oldPaths[] = $setting?->value;

                    $this->upsertSettings($definition['group'], [
                        $definition['key'] => $path,
                    ]);

                    TenantSetting::query()
                        ->where('group', $definition['group'])
                        ->where('key', $definition['legacy_key'])
                        ->delete();
                }
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                $this->media->delete($path);
            }

            throw $exception;
        }

        foreach ($oldPaths as $path) {
            $this->media->delete(is_string($path) ? $path : null);
        }

        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteImage(string $image): array
    {
        $definition = $this->imageDefinitions()[$image] ?? null;

        abort_if($definition === null, 404);

        $setting = $this->setting($definition['group'], $definition['key']);

        if ($setting !== null) {
            $path = is_string($setting->value) ? $setting->value : null;
            $setting->delete();
            $this->media->delete($path);
        }

        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    private function branchPayload(?Branch $branch): array
    {
        if ($branch === null) {
            return [];
        }

        return [
            ...$branch->only([
                'id',
                'name',
                'code',
                'email',
                'phone',
                'address',
                'latitude',
                'longitude',
                'is_active',
            ]),
            'settings' => $this->appendMediaUrls(
                $this->settings('branch', (string) $branch->getKey().'.'),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(string $group, ?string $keyPrefix = null): array
    {
        return TenantSetting::query()
            ->where('group', $group)
            ->when($keyPrefix !== null, fn ($query) => $query->where('key', 'like', $keyPrefix.'%'))
            ->get()
            ->mapWithKeys(function (TenantSetting $setting) use ($keyPrefix): array {
                $key = $keyPrefix === null
                    ? $setting->key
                    : substr($setting->key, strlen($keyPrefix));

                return [$key => $setting->value];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateRegular(array $attributes): void
    {
        if ($attributes === []) {
            return;
        }

        $profileAttributes = Arr::only($attributes, [
            'business_name',
            'timezone',
            'currency',
            'operating_hours',
        ]);

        if ($profileAttributes !== []) {
            TenantBusinessProfile::query()->firstOrFail()->update($profileAttributes);
        }

        $this->upsertSettings('regular', Arr::except($attributes, [
            'business_name',
            'timezone',
            'currency',
            'operating_hours',
        ]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateBranch(array $attributes): void
    {
        if ($attributes === [] || ! app()->bound('currentBranch')) {
            return;
        }

        app('currentBranch')->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateRentalEngine(array $attributes): void
    {
        if ($attributes !== []) {
            RentalConfiguration::query()->firstOrFail()->update($attributes);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function upsertSettings(string $group, array $settings): void
    {
        $tenantId = (string) app('currentTenant')->getTenantKey();

        foreach ($settings as $key => $value) {
            TenantSetting::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'group' => $group,
                    'key' => $key,
                ],
                ['value' => $value],
            );
        }
    }

    private function setting(string $group, string $key): ?TenantSetting
    {
        return TenantSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function appendMediaUrls(array $settings): array
    {
        foreach (['logo', 'favicon', 'invoice_logo'] as $name) {
            $path = $settings[$name.'_path'] ?? null;
            unset($settings[$name.'_url']);

            if (is_string($path)) {
                $settings[$name.'_url'] = $this->media->url($path);
            }
        }

        return $settings;
    }

    /**
     * @return array<string, array{group: string, key: string, legacy_key: string, directory: string}>
     */
    private function imageDefinitions(): array
    {
        $branchId = (string) app('currentBranch')->getKey();

        return [
            'logo' => [
                'group' => 'branding',
                'key' => 'logo_path',
                'legacy_key' => 'logo_url',
                'directory' => 'branding',
            ],
            'favicon' => [
                'group' => 'branding',
                'key' => 'favicon_path',
                'legacy_key' => 'favicon_url',
                'directory' => 'branding',
            ],
            'invoice_logo' => [
                'group' => 'branding',
                'key' => 'invoice_logo_path',
                'legacy_key' => 'invoice_logo_url',
                'directory' => 'branding',
            ],
            'branch_logo' => [
                'group' => 'branch',
                'key' => $branchId.'.logo_path',
                'legacy_key' => $branchId.'.logo_url',
                'directory' => "branches/{$branchId}",
            ],
        ];
    }
}
