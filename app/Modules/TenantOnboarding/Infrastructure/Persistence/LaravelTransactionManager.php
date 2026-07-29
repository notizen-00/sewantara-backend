<?php

namespace App\Modules\TenantOnboarding\Infrastructure\Persistence;

use App\Modules\TenantOnboarding\Contracts\TransactionManager;
use Closure;
use Illuminate\Support\Facades\DB;

class LaravelTransactionManager implements TransactionManager
{
    public function run(Closure $operation): mixed
    {
        return DB::transaction($operation, attempts: 3);
    }
}
