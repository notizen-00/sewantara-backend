<?php

namespace App\Modules\RegistrationVerification\Application;

use App\Modules\RegistrationVerification\Application\Exceptions\OtpCodeInvalid;
use App\Modules\RegistrationVerification\Application\Exceptions\OtpRequestThrottled;
use App\Modules\RegistrationVerification\Infrastructure\Mail\RegistrationOtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class RegistrationOtp
{
    public function request(string $email): void
    {
        $email = $this->normalize($email);
        $cooldownKey = $this->cooldownKey($email);

        if (Cache::has($cooldownKey)) {
            throw new OtpRequestThrottled;
        }

        $ttlMinutes = (int) config('registration-otp.ttl_minutes', 5);
        $code = (string) random_int(100000, 999999);

        Cache::put(
            $this->codeKey($email),
            ['hash' => hash('sha256', $code), 'attempts' => 0],
            now()->addMinutes($ttlMinutes),
        );

        Cache::put(
            $cooldownKey,
            true,
            now()->addSeconds((int) config('registration-otp.resend_seconds', 60)),
        );

        Mail::to($email)->send(new RegistrationOtpMail($code, $ttlMinutes));
    }

    public function verify(string $email, string $code): void
    {
        $email = $this->normalize($email);
        $key = $this->codeKey($email);
        $record = Cache::get($key);
        $maxAttempts = (int) config('registration-otp.max_attempts', 5);

        if (! is_array($record) || ! isset($record['hash'], $record['attempts'])) {
            throw new OtpCodeInvalid;
        }

        if ((int) $record['attempts'] >= $maxAttempts) {
            Cache::forget($key);

            throw new OtpCodeInvalid;
        }

        if (! hash_equals((string) $record['hash'], hash('sha256', $code))) {
            $record['attempts'] = (int) $record['attempts'] + 1;

            Cache::put(
                $key,
                $record,
                now()->addMinutes((int) config('registration-otp.ttl_minutes', 5)),
            );

            throw new OtpCodeInvalid;
        }

        Cache::forget($key);
        Cache::forget($this->cooldownKey($email));

        Cache::put(
            $this->verifiedKey($email),
            true,
            now()->addMinutes((int) config('registration-otp.verified_ttl_minutes', 30)),
        );
    }

    public function isVerified(string $email): bool
    {
        return Cache::has($this->verifiedKey($this->normalize($email)));
    }

    public function forgetVerified(string $email): void
    {
        Cache::forget($this->verifiedKey($this->normalize($email)));
    }

    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function codeKey(string $email): string
    {
        return 'registration_otp:code:'.hash('sha256', $email);
    }

    private function cooldownKey(string $email): string
    {
        return 'registration_otp:cooldown:'.hash('sha256', $email);
    }

    private function verifiedKey(string $email): string
    {
        return 'registration_otp:verified:'.hash('sha256', $email);
    }
}
