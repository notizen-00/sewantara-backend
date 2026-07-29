<?php

namespace Database\Seeders\Concerns;

use App\Models\Tenant;
use Closure;
use Database\Seeders\DemoTenantRegistrationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait SeedsDemoTenant
{
    /** @var array<int, string> */
    private array $incrementingTables = [
        'roles',
        'permissions',
        'branches',
        'customers',
        'categories',
        'products',
        'product_units',
        'inventory_stocks',
    ];

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
    ): int|string {
        $identity = ['tenant_id' => $tenantId, ...$identity];

        if (in_array($table, $this->incrementingTables, true)) {
            return $this->upsertIncrementingRow(
                $table,
                $identity,
                $attributes,
            );
        }

        return $this->upsertUuidRow($table, $identity, $attributes);
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

        return (int) DB::table($table)->insertGetId([
            ...$identity,
            ...$attributes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertUuidRow(
        string $table,
        array $identity,
        array $attributes,
    ): string {
        $existing = DB::table($table)->where($identity)->first(['id']);
        $now = now();

        if ($existing !== null) {
            DB::table($table)->where('id', $existing->id)->update([
                ...$attributes,
                'updated_at' => $now,
            ]);

            return (string) $existing->id;
        }

        $id = (string) Str::uuid();

        DB::table($table)->insert([
            'id' => $id,
            ...$identity,
            ...$attributes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    protected function tenantRowId(
        string $table,
        string $tenantId,
        string $column,
        mixed $value,
    ): int|string {
        $id = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->where($column, $value)
            ->value('id');

        return in_array($table, $this->incrementingTables, true)
            ? (int) $id
            : (string) $id;
    }
}
