<?php

namespace App\Modules\RegistrationVerification\Application\Exceptions;

use RuntimeException;

class OtpRequestThrottled extends RuntimeException {}
