<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantImagesRequest;
use App\Http\Requests\Tenant\UpdateTenantSettingsRequest;
use App\Modules\TenantSettings\Application\ManageTenantSettings;
use Illuminate\Http\JsonResponse;

class TenantSettingController extends Controller
{
    public function show(ManageTenantSettings $settings): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $settings->payload(),
        ]);
    }

    public function update(
        UpdateTenantSettingsRequest $request,
        ManageTenantSettings $settings,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Pengaturan tenant berhasil diperbarui.',
            'data' => $settings->update($request->validated()),
        ]);
    }

    public function updateImages(
        UpdateTenantImagesRequest $request,
        ManageTenantSettings $settings,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Gambar tenant berhasil diperbarui.',
            'data' => $settings->updateImages($request->allFiles()),
        ]);
    }

    public function destroyImage(
        string $image,
        ManageTenantSettings $settings,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Gambar tenant berhasil dihapus.',
            'data' => $settings->deleteImage($image),
        ]);
    }
}
