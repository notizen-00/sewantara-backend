<?php

namespace App\Modules\TenantAuthentication\Application;

use App\Models\CentralUser;
use App\Models\Tenant;
use App\Modules\TenantAuthentication\Application\Exceptions\InactiveTenantUser;
use App\Modules\TenantAuthentication\Application\Exceptions\InvalidTenantCredentials;

class ResolveTenantFromCentralAccount
{
    public function execute(string $email): Tenant
    {
        $account = CentralUser::query()
            ->with('tenant')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first();

        if (! $account || ! $account->tenant) {
            throw new InvalidTenantCredentials;
        }

        if (! $account->is_active) {
            throw new InactiveTenantUser;
        }

        return $account->tenant;
    }
}
