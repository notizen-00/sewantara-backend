<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TenantLoginRequest;
use App\Modules\TenantAuthentication\Application\Exceptions\InactiveTenantUser;
use App\Modules\TenantAuthentication\Application\Exceptions\InvalidTenantCredentials;
use App\Modules\TenantAuthentication\Application\ManageTenantAuthentication;
use App\Modules\TenantAuthentication\Application\ResolveTenantFromCentralAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

class TenantAuthController extends Controller
{
    public function login(
        TenantLoginRequest $request,
        ManageTenantAuthentication $authentication,
        ResolveTenantFromCentralAccount $tenantResolver,
        Tenancy $tenancy,
    ): JsonResponse {
        try {
            $email = $request->string('email')->toString();
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
                $result = $authentication->login(
                    email: $email,
                    password: $request->string('password')->toString(),
                    deviceName: $request->string('device_name')->toString()
                        ?: 'api-client',
                );
            } finally {
                app()->forgetInstance('currentTenant');
                $tenancy->end();
            }
        } catch (InvalidTenantCredentials $exception) {
            return $this->error(
                'INVALID_CREDENTIALS',
                $exception->getMessage(),
                401,
            );
        } catch (InactiveTenantUser $exception) {
            return $this->error(
                'USER_INACTIVE',
                $exception->getMessage(),
                403,
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil masuk.',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $result['access_token'],
                'user' => $result['user'],
            ],
        ]);
    }

    public function logout(
        Request $request,
        ManageTenantAuthentication $authentication,
    ): JsonResponse {
        $authentication->logout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar.',
            'data' => null,
        ]);
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
}
