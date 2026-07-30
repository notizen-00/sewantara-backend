<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Tenancy;

use App\Models\Tenant;
use App\Modules\TenantOnboarding\Contracts\TenantEnvironmentProvisioner;
use Illuminate\Support\Facades\Artisan;
use LogicException;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Throwable;

class StanclTenantEnvironmentProvisioner implements TenantEnvironmentProvisioner
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly InitializeTenantDatabase $initializeTenantDatabase,
    ) {}

    public function provision(string $tenantId): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        if ($tenant->provisioned_at !== null) {
            return;
        }

        $tenant->forceFill([
            'provisioning_status' => 'provisioning',
            'provisioning_error' => null,
        ])->save();

        try {
            $database = $tenant->database();
            $manager = $database->manager();

            if (! $manager->databaseExists($database->getName())) {
                (new CreateDatabase($tenant))->handle($this->databaseManager);
            }

            $exitCode = Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->getTenantKey()],
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new LogicException(
                    'Tenant migration gagal: '.Artisan::output(),
                );
            }

            $owner = $tenant->getInternal('pending_owner');

            if (! is_array($owner) || empty($owner['id'])) {
                throw new LogicException('Data owner tenant belum tersedia.');
            }

            $tenant->run(
                fn () => $this->initializeTenantDatabase->handle(
                    (string) $tenant->getTenantKey(),
                    (string) $tenant->name,
                    $owner,
                ),
            );

            $tenant->setInternal('pending_owner', null);
            $tenant->forceFill([
                'status' => 'active',
                'activated_at' => now(),
                'provisioning_status' => 'provisioned',
                'provisioned_at' => now(),
                'provisioning_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $tenant->forceFill([
                'status' => 'pending',
                'provisioning_status' => 'failed',
                'provisioning_error' => mb_substr(
                    $exception->getMessage(),
                    0,
                    2000,
                ),
            ])->save();

            throw $exception;
        }
    }
}
