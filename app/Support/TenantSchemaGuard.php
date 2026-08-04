<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantSchemaGuard
{
    public function assertReady(Tenant $tenant): void
    {
        $connection = DB::connection('tenant');

        $this->assertExpectedSchema($connection, $tenant);

        $profileMatches = $connection->table('tenant_business_profiles')
            ->where('tenant_id', (string) $tenant->getKey())
            ->exists();

        if (! $profileMatches) {
            throw new RuntimeException('Tenant database marker does not match.');
        }
    }

    private function assertExpectedSchema(
        ConnectionInterface $connection,
        Tenant $tenant,
    ): void {
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $row = $connection->selectOne(
            'SELECT current_schema() AS schema_name',
        );
        $actual = is_object($row)
            ? ($row->schema_name ?? null)
            : ($row['schema_name'] ?? null);
        $expected = $tenant->database()->getName();

        if (! is_string($actual)
            || ! is_string($expected)
            || $actual !== $expected) {
            throw new RuntimeException('Unexpected tenant database schema.');
        }
    }
}
