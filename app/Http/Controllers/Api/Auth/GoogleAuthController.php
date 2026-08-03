<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Modules\TenantAuthentication\Application\Exceptions\InactiveTenantUser;
use App\Modules\TenantAuthentication\Application\Exceptions\InvalidTenantCredentials;
use App\Modules\TenantAuthentication\Application\ManageTenantAuthentication;
use App\Modules\TenantAuthentication\Application\ResolveTenantFromCentralAccount;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Stancl\Tenancy\Tenancy;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $deviceName = trim((string) $request->query('device_name', 'web'));

        $request->session()->put(
            'google_auth_device_name',
            mb_substr($deviceName !== '' ? $deviceName : 'web', 0, 100),
        );

        return Socialite::driver('google')->redirect();
    }

    public function callback(
        Request $request,
        ResolveTenantFromCentralAccount $tenantResolver,
        ManageTenantAuthentication $authentication,
        Tenancy $tenancy,
    ): JsonResponse|RedirectResponse {
        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->error(
                'OAUTH_STATE_INVALID',
                'Sesi autentikasi Google tidak valid atau sudah kedaluwarsa.',
                419,
            );
        } catch (GuzzleException $exception) {
            report($exception);

            return $this->error(
                'GOOGLE_AUTH_UNAVAILABLE',
                'Layanan autentikasi Google sedang tidak dapat diakses.',
                502,
            );
        }

        $email = $googleUser->getEmail();

        if (! is_string($email)
            || trim($email) === ''
            || ($googleUser->user['verified_email'] ?? false) !== true) {
            return $this->error(
                'GOOGLE_EMAIL_UNVERIFIED',
                'Akun Google harus memiliki alamat email yang terverifikasi.',
                403,
            );
        }

        try {
            $tenant = $tenantResolver->execute($email);

            if (! in_array($tenant->status, ['onboarding', 'active'], true)) {
                return $this->error(
                    $tenant->status === 'suspended'
                        ? 'TENANT_SUSPENDED'
                        : 'TENANT_INACCESSIBLE',
                    'Akun usaha tidak dapat diakses.',
                    423,
                );
            }

            $tenancy->initialize($tenant);
            app()->instance('currentTenant', $tenant);

            try {
                $result = $authentication->loginWithVerifiedEmail(
                    email: $email,
                    deviceName: (string) $request->session()->pull(
                        'google_auth_device_name',
                        'web',
                    ),
                );
            } finally {
                app()->forgetInstance('currentTenant');
                $tenancy->end();
            }
        } catch (InvalidTenantCredentials) {
            return $this->error(
                'GOOGLE_ACCOUNT_NOT_REGISTERED',
                'Email Google ini belum terdaftar pada akun tenant.',
                401,
            );
        } catch (InactiveTenantUser $exception) {
            return $this->error(
                'USER_INACTIVE',
                $exception->getMessage(),
                403,
            );
        }

        $payload = [
            'success' => true,
            'message' => 'Berhasil masuk dengan Google.',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $result['access_token'],
                'user' => $result['user'],
            ],
        ];

        $frontendCallback = trim((string) config(
            'services.google.frontend_callback',
        ));

        if ($frontendCallback === '') {
            return response()->json($payload);
        }

        $exchangeCode = Str::random(64);

        Cache::put(
            $this->exchangeCacheKey($exchangeCode),
            $payload,
            now()->addSeconds((int) config(
                'services.google.exchange_ttl',
                60,
            )),
        );

        return redirect()->away(
            $frontendCallback
            .(str_contains($frontendCallback, '?') ? '&' : '?')
            .http_build_query(['code' => $exchangeCode]),
        );
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:64'],
        ]);

        $payload = Cache::pull(
            $this->exchangeCacheKey($validated['code']),
        );

        if (! is_array($payload)) {
            return $this->error(
                'GOOGLE_AUTH_CODE_INVALID',
                'Kode autentikasi Google tidak valid atau sudah kedaluwarsa.',
                401,
            );
        }

        return response()->json($payload);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => null,
            ],
        ], $status);
    }

    private function exchangeCacheKey(string $code): string
    {
        return 'google_auth_exchange:'.hash('sha256', $code);
    }
}
