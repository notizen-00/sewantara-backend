<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Tenancy;

use App\Models\Tenant;
use App\Modules\ProductEngine\Contracts\TenantEngineProvisioner;
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
        private readonly TenantEngineProvisioner $engines,
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
                    'Migrasi basis data akun usaha gagal: '.Artisan::output(),
                );
            }

            $owner = $tenant->getInternal('pending_owner');
            $onboarding = $tenant->getInternal('pending_onboarding');

            if (! is_array($owner) || empty($owner['id'])) {
                throw new LogicException('Data pemilik akun usaha belum tersedia.');
            }

            if (! is_array($onboarding)
                || ! is_array($onboarding['configuration'] ?? null)) {
                throw new LogicException('Konfigurasi penyiapan awal akun usaha belum tersedia.');
            }

            $tenant->run(
                fn () => $this->initializeTenantDatabase->handle(
                    (string) $tenant->getTenantKey(),
                    (string) $tenant->name,
                    $owner,
                    $onboarding,
                    (string) $tenant->timezone,
                    (string) $tenant->currency,
                ),
            );

            $this->engines->enableDefaults((string) $tenant->getTenantKey());

            $tenant->setInternal('pending_owner', null);
            $tenant->setInternal('pending_onboarding', null);
            $tenant->forceFill([
                'status' => 'onboarding',
                'activated_at' => null,
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
