<?php

namespace Database\Seeders\Concerns;

use App\Models\Tenant;
use Closure;
use Database\Seeders\DemoTenantRegistrationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait SeedsDemoTenant
{
    protected function withinDemoTenant(Closure $callback): void
    {
        $tenant = Tenant::query()
            ->where('slug', DemoTenantRegistrationSeeder::TENANT_SLUG)
            ->firstOrFail();

        $tenant->run(
            fn () => DB::transaction(
                fn () => $callback((string) $tenant->getKey()),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertTenantRow(
        string $table,
        string $tenantId,
        array $identity,
        array $attributes,
    ): int {
        $identity = ['tenant_id' => $tenantId, ...$identity];

        return $this->upsertIncrementingRow(
            $table,
            $identity,
            $attributes,
        );
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertIncrementingRow(
        string $table,
        array $identity,
        array $attributes,
    ): int {
        $existing = DB::table($table)->where($identity)->first(['id']);
        $now = now();

        if ($existing !== null) {
            DB::table($table)->where('id', $existing->id)->update([
                ...$attributes,
                'updated_at' => $now,
            ]);

            return (int) $existing->id;
        }

        $insertDefaults = [];

        if (Schema::hasColumn($table, 'public_id')
            && ! array_key_exists('public_id', $attributes)) {
            $insertDefaults['public_id'] = (string) Str::uuid();
        }

        if ($table === 'products'
            && Schema::hasColumn($table, 'published_at')
            && ! array_key_exists('published_at', $attributes)) {
            $insertDefaults['published_at'] = $now;
        }

        return (int) DB::table($table)->insertGetId([
            ...$identity,
            ...$insertDefaults,
            ...$attributes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function tenantRowId(
        string $table,
        string $tenantId,
        string $column,
        mixed $value,
    ): int {
        $id = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->where($column, $value)
            ->value('id');

        return (int) $id;
    }
}
