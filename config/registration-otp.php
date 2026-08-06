<?php

return [
    'ttl_minutes' => (int) env('REGISTRATION_OTP_TTL_MINUTES', 5),
    'resend_seconds' => (int) env('REGISTRATION_OTP_RESEND_SECONDS', 60),
    'max_attempts' => (int) env('REGISTRATION_OTP_MAX_ATTEMPTS', 5),
    'verified_ttl_minutes' => (int) env('REGISTRATION_OTP_VERIFIED_TTL_MINUTES', 30),
];
