<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantSettingsRequest;
use App\Models\Branch;
use App\Models\RentalConfiguration;
use App\Models\TenantBusinessProfile;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TenantSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->payload(),
        ]);
    }

    public function update(UpdateTenantSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $this->updateRegular($validated['regular'] ?? []);
            $this->upsertSettings('branding', $validated['branding'] ?? []);
            $this->updateBranch($validated['branch'] ?? []);
            $this->updateRentalEngine($validated['rental_engine'] ?? []);
        });

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan tenant berhasil diperbarui.',
            'data' => $this->payload(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $profile = TenantBusinessProfile::query()->first();
        $branch = app()->bound('currentBranch') ? app('currentBranch') : null;

        return [
            'regular' => array_filter([
                'business_name' => $profile?->business_name,
                'timezone' => $profile?->timezone,
                'currency' => $profile?->currency,
                'operating_hours' => $profile?->operating_hours,
                ...$this->settings('regular'),
            ], static fn ($value): bool => $value !== null),
            'branding' => $this->settings('branding'),
            'branch' => $this->branchPayload($branch),
            'rental_engine' => RentalConfiguration::query()->firstOrFail(),
        ];
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
            'settings' => $this->settings('branch', (string) $branch->getKey().'.'),
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

        $branch = app('currentBranch');
        $branchAttributes = Arr::except($attributes, ['logo_url']);

        if ($branchAttributes !== []) {
            $branch->update($branchAttributes);
        }

        if (array_key_exists('logo_url', $attributes)) {
            $this->upsertSettings('branch', [
                $branch->getKey().'.logo_url' => $attributes['logo_url'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateRentalEngine(array $attributes): void
    {
        if ($attributes === []) {
            return;
        }

        RentalConfiguration::query()->firstOrFail()->update($attributes);
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
}
