<?php

namespace App\Modules\Payments\Application;

use App\Models\Payment;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterPaymentWebhookRoute
{
    public function register(
        string $tenantId,
        Payment $payment,
        string $provider,
        string $currency = 'IDR',
    ): void {
        $connection = $this->centralConnection();

        if (! $connection->getSchemaBuilder()->hasTable('payment_webhook_routes')) {
            return;
        }

        $now = now();
        $identity = [
            'provider' => $provider,
            'external_reference' => $payment->payment_number,
        ];
        $connection->table('payment_webhook_routes')->insertOrIgnore([
            ...$identity,
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'payment_public_id' => $payment->public_id,
            'expected_amount' => $payment->amount,
            'currency' => strtoupper($currency),
            'status' => 'active',
            'expires_at' => $payment->expired_at,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $connection->table('payment_webhook_routes')
            ->where($identity)
            ->update([
                'tenant_id' => $tenantId,
                'payment_public_id' => $payment->public_id,
                'expected_amount' => $payment->amount,
                'currency' => strtoupper($currency),
                'status' => 'active',
                'expires_at' => $payment->expired_at,
                'updated_at' => $now,
            ]);
    }

    public function markFailed(string $provider, string $reference): void
    {
        $connection = $this->centralConnection();

        if (! $connection->getSchemaBuilder()->hasTable('payment_webhook_routes')) {
            return;
        }

        $connection->table('payment_webhook_routes')
            ->where('provider', $provider)
            ->where('external_reference', $reference)
            ->update(['status' => 'failed', 'updated_at' => now()]);
    }

    private function centralConnection(): ConnectionInterface
    {
        return DB::connection((string) config('tenancy.database.central_connection'));
    }
}
