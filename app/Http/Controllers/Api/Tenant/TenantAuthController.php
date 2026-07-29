<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TenantLoginRequest;
use App\Modules\TenantAuthentication\Application\Exceptions\InactiveTenantUser;
use App\Modules\TenantAuthentication\Application\Exceptions\InvalidTenantCredentials;
use App\Modules\TenantAuthentication\Application\ManageTenantAuthentication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantAuthController extends Controller
{
    public function login(
        TenantLoginRequest $request,
        ManageTenantAuthentication $authentication,
    ): JsonResponse {
        try {
            $result = $authentication->login(
                email: $request->string('email')->toString(),
                password: $request->string('password')->toString(),
                deviceName: $request->string('device_name')->toString()
                    ?: 'api-client',
            );
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
            'message' => 'Login berhasil.',
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
            'message' => 'Logout berhasil.',
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
