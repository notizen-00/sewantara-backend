<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestRegistrationOtpRequest;
use App\Http\Requests\Auth\VerifyRegistrationOtpRequest;
use App\Modules\RegistrationVerification\Application\Exceptions\OtpCodeInvalid;
use App\Modules\RegistrationVerification\Application\Exceptions\OtpRequestThrottled;
use App\Modules\RegistrationVerification\Application\RegistrationOtp;
use Illuminate\Http\JsonResponse;

class RegistrationOtpController extends Controller
{
    public function request(
        RequestRegistrationOtpRequest $request,
        RegistrationOtp $otp,
    ): JsonResponse {
        try {
            $otp->request((string) $request->validated('email'));
        } catch (OtpRequestThrottled) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'OTP_REQUEST_THROTTLED',
                    'message' => 'Mohon tunggu sebentar sebelum meminta kode verifikasi baru.',
                    'details' => null,
                ],
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kode verifikasi telah dikirim ke email Anda.',
            'data' => null,
            'meta' => null,
        ]);
    }

    public function verify(
        VerifyRegistrationOtpRequest $request,
        RegistrationOtp $otp,
    ): JsonResponse {
        try {
            $otp->verify(
                (string) $request->validated('email'),
                (string) $request->validated('code'),
            );
        } catch (OtpCodeInvalid) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'OTP_CODE_INVALID',
                    'message' => 'Kode verifikasi salah atau sudah kedaluwarsa.',
                    'details' => null,
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email berhasil diverifikasi.',
            'data' => null,
            'meta' => null,
        ]);
    }
}
