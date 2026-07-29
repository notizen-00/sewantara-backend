<?php

namespace App\Modules\TenantAuthentication\Application\Exceptions;

use RuntimeException;

class InactiveTenantUser extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Akun pengguna sedang tidak aktif.');
    }
}
