<?php

namespace App\Modules\TenantAuthentication\Application\Exceptions;

use RuntimeException;

class InvalidTenantCredentials extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email atau password tidak valid.');
    }
}
