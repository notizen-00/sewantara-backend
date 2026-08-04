<?php

namespace App\Modules\PublicApi\Application;

use App\Models\IdempotencyRecord;
use App\Modules\PublicApi\Data\IdempotencyOutcome;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use App\Modules\PublicApi\Support\CanonicalPayload;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PublicIdempotency
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(IdempotencyRecord): array{data: array<string, mixed>, status: int}  $create
     * @param  Closure(IdempotencyRecord): array{data: array<string, mixed>, status: int}  $resume
     */
    public function execute(
        string $tenantId,
        string $endpoint,
        string $key,
        array $payload,
        Closure $create,
        Closure $resume,
    ): IdempotencyOutcome {
        $requestHash = CanonicalPayload::hash($payload);
        $lock = $this->lock($tenantId, $endpoint, $key);

        if (! $lock->get()) {
            throw new PublicApiException(
                'IDEMPOTENCY_IN_PROGRESS',
                'Permintaan dengan kunci yang sama sedang diproses.',
                409,
            );
        }

        try {
            $record = IdempotencyRecord::query()
                ->where('tenant_id', $tenantId)
                ->where('endpoint', $endpoint)
                ->where('idempotency_key', $key)
                ->first();

            if ($record !== null && $record->expires_at?->isPast()) {
                $record->delete();
                $record = null;
            }

            if ($record !== null) {
                $this->guardRequestHash($record, $requestHash);

                if ($record->status === 'completed'
                    && is_array($record->response_body)
                    && $record->response_status !== null) {
                    return new IdempotencyOutcome(
                        $record->response_body,
                        $record->response_status,
                        true,
                    );
                }

                if ($record->status === 'in_progress'
                    && filled($record->resource_id)) {
                    $result = $resume($record);
                    $this->complete($record, $result);

                    return new IdempotencyOutcome(
                        $result['data'],
                        $result['status'],
                        true,
                    );
                }

                throw new PublicApiException(
                    'IDEMPOTENCY_IN_PROGRESS',
                    'Permintaan dengan kunci yang sama sedang diproses.',
                    409,
                );
            }

            $record = IdempotencyRecord::query()->create([
                'tenant_id' => $tenantId,
                'idempotency_key' => $key,
                'endpoint' => $endpoint,
                'request_hash' => $requestHash,
                'status' => 'in_progress',
                'expires_at' => now()->addHours(max(
                    1,
                    (int) config('public-api.idempotency_ttl_hours', 24),
                )),
            ]);

            try {
                $result = $create($record);
            } catch (Throwable $exception) {
                if (blank($record->fresh()?->resource_id)) {
                    $record->delete();
                }

                throw $exception;
            }

            $this->complete($record, $result);

            return new IdempotencyOutcome(
                $result['data'],
                $result['status'],
                false,
            );
        } finally {
            $lock->release();
        }
    }

    private function lock(string $tenantId, string $endpoint, string $key): Lock
    {
        $store = config('public-api.idempotency_cache_store');

        return Cache::store(is_string($store) ? $store : null)->lock(
            'public-idempotency:'.hash('sha256', implode('|', [
                $tenantId,
                $endpoint,
                $key,
            ])),
            60,
        );
    }

    private function guardRequestHash(
        IdempotencyRecord $record,
        string $requestHash,
    ): void {
        if (! hash_equals($record->request_hash, $requestHash)) {
            throw new PublicApiException(
                'IDEMPOTENCY_CONFLICT',
                'Kunci idempotensi telah digunakan untuk data yang berbeda.',
                409,
            );
        }
    }

    /**
     * @param  array{data: array<string, mixed>, status: int}  $result
     */
    private function complete(IdempotencyRecord $record, array $result): void
    {
        $record->forceFill([
            'status' => 'completed',
            'response_status' => $result['status'],
            'response_body' => $result['data'],
        ])->save();
    }
}
