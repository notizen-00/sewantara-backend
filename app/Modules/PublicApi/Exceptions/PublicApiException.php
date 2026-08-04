<?php

namespace App\Modules\PublicApi\Exceptions;

use RuntimeException;

class PublicApiException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>|null  $fields
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
        public readonly ?array $fields = null,
    ) {
        parent::__construct($message);
    }
}
