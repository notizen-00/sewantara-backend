<?php

namespace Database\Seeders\Support;

use App\Models\Tenant;
use Database\Seeders\DemoTenantRegistrationSeeder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrphanedDemoTenantSchemaCleaner
{
    /**
     * @return array<int, string>
     */
    public function clean(): array
    {
        $connection = DB::connection(
            config('tenancy.database.central_connection'),
        );

        if ($connection->getDriverName() !== 'pgsql') {
            return [];
        }

        $activeSchemas = Tenant::withTrashed()
            ->get()
            ->map(
                fn (Tenant $tenant): string => (string) $tenant
                    ->database()
                    ->getName(),
            )
            ->all();
        $deletedSchemas = [];

        foreach ($this->tenantSchemas($connection) as $schema) {
            if (
                in_array($schema, $activeSchemas, true)
                || ! $this->isSafeTenantSchemaName($schema)
                || ! $this->belongsToDemoTenant($connection, $schema)
            ) {
                continue;
            }

            $connection->statement(
                sprintf('DROP SCHEMA %s CASCADE', $this->quote($schema)),
            );
            $deletedSchemas[] = $schema;
        }

        return $deletedSchemas;
    }

    public function isSafeTenantSchemaName(
        string $schema,
        ?string $prefix = null,
        ?string $suffix = null,
    ): bool {
        $prefix ??= (string) config('tenancy.database.prefix', 'tenant');
        $suffix ??= (string) config('tenancy.database.suffix', '');
        $pattern = sprintf(
            '/^%s[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}%s$/i',
            preg_quote($prefix, '/'),
            preg_quote($suffix, '/'),
        );

        return preg_match($pattern, $schema) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function tenantSchemas(Connection $connection): array
    {
        $prefix = (string) config('tenancy.database.prefix', 'tenant');
        $rows = $connection->select(
            <<<'SQL'
                SELECT schema_name
                FROM information_schema.schemata
                WHERE schema_name LIKE ?
                ORDER BY schema_name
            SQL,
            [$prefix.'%'],
        );

        return array_map(
            fn (object $row): string => (string) $row->schema_name,
            $rows,
        );
    }

    private function belongsToDemoTenant(
        Connection $connection,
        string $schema,
    ): bool {
        try {
            $result = $connection->selectOne(
                sprintf(
                    'SELECT EXISTS (SELECT 1 FROM %s.users WHERE email = ?) AS found',
                    $this->quote($schema),
                ),
                [DemoTenantRegistrationSeeder::OWNER_EMAIL],
            );
        } catch (Throwable) {
            return false;
        }

        return filter_var($result?->found, FILTER_VALIDATE_BOOL);
    }

    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
