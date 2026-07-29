<?php

namespace App\Modules\TenantOnboarding\Contracts;

use Closure;

interface TransactionManager
{
    public function run(Closure $operation): mixed;
}
