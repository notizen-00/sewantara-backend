<?php

namespace App\Modules\Payments\Application;

use App\Models\Tenant;
use App\Support\TenantSchemaGuard;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Stancl\Tenancy\Tenancy;
use Throwable;

class HandleCentralPaymentWebhook
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly TenantSchemaGuard $schemaGuard,
        private readonly HandlePaymentGatewayNotification $handler,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{accepted: bool, duplicate: bool, reference: ?string, status: ?string}
     */
    public function execute(string $provider, array $payload): array
    {
        $provider = strtolower(trim($provider));

        if (! preg_match('/^[a-z0-9_-]{2,50}$/', $provider)) {
            throw new InvalidArgumentException('Provider webhook tidak valid.');
        }

        $reference = $this->externalReference($provider, $payload);
        $eventId = $this->eventId($provider, $payload);
        $payloadHash = $this->payloadHash($payload);
        $central = $this->centralConnection();

        $route = $central->table('payment_webhook_routes')
            ->where('provider', $provider)
            ->where('external_reference', $reference)
            ->where('status', 'active')
            ->first();

        if ($route === null) {
            return [
                'accepted' => true,
                'duplicate' => false,
                'reference' => null,
                'status' => null,
            ];
        }

        $existingEvent = $central->table('payment_webhook_events')
            ->where('provider', $provider)
            ->where('provider_event_id', $eventId)
            ->first();

        if ($existingEvent !== null
            && $existingEvent->payload_hash !== $payloadHash) {
            throw new InvalidArgumentException('Identitas webhook tidak konsisten.');
        }

        if ($existingEvent?->status === 'processed') {
            return [
                'accepted' => true,
                'duplicate' => true,
                'reference' => $reference,
                'status' => 'processed',
            ];
        }

        $tenant = Tenant::query()
            ->whereKey($route->tenant_id)
            ->where('provisioning_status', 'provisioned')
            ->whereNotNull('provisioned_at')
            ->first();

        if ($tenant === null) {
            throw new RuntimeException('Tenant tujuan webhook tidak tersedia.');
        }

        try {
            $this->tenancy->initialize($tenant);
            $this->schemaGuard->assertReady($tenant);
            $payment = $this->handler->execute(
                $provider,
                $payload,
                (string) $route->payment_public_id,
            );

            $this->recordEvent(
                $central,
                $provider,
                $eventId,
                (string) $tenant->getKey(),
                $reference,
                $payloadHash,
                $payload,
                'processed',
            );

            if (in_array($payment->status, ['paid', 'failed', 'expired'], true)) {
                $central->table('payment_webhook_routes')
                    ->where('id', $route->id)
                    ->update([
                        'status' => 'completed',
                        'updated_at' => now(),
                    ]);
            }

            return [
                'accepted' => true,
                'duplicate' => false,
                'reference' => $reference,
                'status' => $payment->status,
            ];
        } catch (Throwable $exception) {
            $this->recordEvent(
                $central,
                $provider,
                $eventId,
                (string) $tenant->getKey(),
                $reference,
                $payloadHash,
                $payload,
                'failed',
                class_basename($exception),
            );

            throw $exception;
        } finally {
            if ($this->tenancy->initialized) {
                $this->tenancy->end();
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function externalReference(string $provider, array $payload): string
    {
        $value = match ($provider) {
            'midtrans' => $payload['order_id'] ?? null,
            default => null,
        };

        if (! is_string($value)
            || $value === ''
            || strlen($value) > 150) {
            throw new InvalidArgumentException('Referensi webhook tidak valid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function eventId(string $provider, array $payload): string
    {
        $value = match ($provider) {
            'midtrans' => $payload['transaction_id'] ?? null,
            default => null,
        };

        return is_string($value) && $value !== ''
            ? substr($value, 0, 200)
            : 'payload-'.substr($this->payloadHash($payload), 0, 56);
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        ConnectionInterface $central,
        string $provider,
        string $eventId,
        string $tenantId,
        string $reference,
        string $payloadHash,
        array $payload,
        string $status,
        ?string $errorCode = null,
    ): void {
        $identity = [
            'provider' => $provider,
            'provider_event_id' => $eventId,
        ];
        $now = now();
        $central->table('payment_webhook_events')->insertOrIgnore([
            ...$identity,
            'tenant_id' => $tenantId,
            'external_reference' => $reference,
            'payload_hash' => $payloadHash,
            'redacted_payload' => json_encode(
                $this->redact($payload),
                JSON_THROW_ON_ERROR,
            ),
            'status' => $status,
            'error_code' => $errorCode,
            'processed_at' => $status === 'processed' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $central->table('payment_webhook_events')
            ->where($identity)
            ->where('payload_hash', $payloadHash)
            ->update([
                'tenant_id' => $tenantId,
                'external_reference' => $reference,
                'redacted_payload' => json_encode(
                    $this->redact($payload),
                    JSON_THROW_ON_ERROR,
                ),
                'status' => $status,
                'error_code' => $errorCode,
                'processed_at' => $status === 'processed' ? $now : null,
                'updated_at' => $now,
            ]);
    }

    /** @return array<string, mixed> */
    private function redact(array $payload): array
    {
        $sensitive = [
            'signature_key',
            'authorization',
            'token',
            'secret',
            'password',
            'card_number',
            'cvv',
        ];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $payload[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->redact($value);
            }
        }

        return $payload;
    }

    private function centralConnection(): ConnectionInterface
    {
        return DB::connection((string) config('tenancy.database.central_connection'));
    }
}
