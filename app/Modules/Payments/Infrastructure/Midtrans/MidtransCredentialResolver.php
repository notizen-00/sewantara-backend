<?php

namespace App\Modules\Payments\Infrastructure\Midtrans;

use App\Models\TenantPaymentMethod;
use LogicException;

class MidtransCredentialResolver
{
    public function resolve(): MidtransCredentials
    {
        $tenantConfiguration = TenantPaymentMethod::query()
            ->where('method', 'midtrans')
            ->where('is_enabled', true)
            ->first()
            ?->configuration;

        $configuration = is_array($tenantConfiguration) ? $tenantConfiguration : [];
        $serverKey = (string) ($configuration['server_key'] ?? config('services.midtrans.server_key'));
        $clientKey = (string) ($configuration['client_key'] ?? config('services.midtrans.client_key'));

        if ($serverKey === '' || $clientKey === '') {
            throw new LogicException('Kredensial Midtrans belum dikonfigurasi.');
        }

        return new MidtransCredentials(
            serverKey: $serverKey,
            clientKey: $clientKey,
            production: filter_var(
                $configuration['is_production'] ?? config('services.midtrans.is_production', false),
                FILTER_VALIDATE_BOOL,
            ),
            secure3ds: filter_var(
                $configuration['is_3ds'] ?? config('services.midtrans.is_3ds', true),
                FILTER_VALIDATE_BOOL,
            ),
        );
    }
}
