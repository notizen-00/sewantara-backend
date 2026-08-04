<?php

namespace App\Modules\PublicApi\Data;

readonly class IdempotencyOutcome
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public int $status,
        public bool $replayed,
    ) {}
}
