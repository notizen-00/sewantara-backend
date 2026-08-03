<?php

namespace App\Modules\TenantAuthentication\Application;

use App\Models\User;
use App\Modules\TenantAuthentication\Application\Exceptions\InactiveTenantUser;
use App\Modules\TenantAuthentication\Application\Exceptions\InvalidTenantCredentials;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ManageTenantAuthentication
{
    /**
     * @return array{access_token: string, user: array<string, mixed>}
     */
    public function login(
        string $email,
        string $password,
        string $deviceName,
    ): array {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new InvalidTenantCredentials;
        }

        if (! $user->is_active) {
            throw new InactiveTenantUser;
        }

        return $this->issueToken($user, $deviceName);
    }

    /**
     * Issue a tenant token after an external identity provider has verified
     * ownership of the email address.
     *
     * @return array{access_token: string, user: array<string, mixed>}
     */
    public function loginWithVerifiedEmail(string $email, string $deviceName): array
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first();

        if (! $user) {
            throw new InvalidTenantCredentials;
        }

        if (! $user->is_active) {
            throw new InactiveTenantUser;
        }

        return $this->issueToken($user, $deviceName);
    }

    /**
     * @return array{access_token: string, user: array<string, mixed>}
     */
    private function issueToken(User $user, string $deviceName): array
    {
        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($deviceName, ['tenant:access']);

        $serializedUser = $user->fresh()->toArray();

        return [
            'access_token' => $token->plainTextToken,
            'user' => $serializedUser,
        ];
    }

    public function logout(?User $user): void
    {
        $user?->currentAccessToken()?->delete();
        Auth::forgetGuards();
    }
}
