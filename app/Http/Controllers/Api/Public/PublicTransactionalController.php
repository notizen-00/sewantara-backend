<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\PublicApi\Exceptions\PublicApiException;
use App\Support\PublicApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class PublicTransactionalController extends Controller
{
    protected function tenantId(Request $request): string
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            throw new PublicApiException(
                'TENANT_NOT_FOUND',
                'Tenant tidak ditemukan.',
                404,
            );
        }

        return (string) $tenant->getTenantKey();
    }

    protected function success(
        Request $request,
        array $data,
        int $status = 200,
    ): JsonResponse {
        return PublicApiResponse::success($request, $data, status: $status)
            ->withHeaders(['Cache-Control' => 'private, no-store']);
    }

    protected function error(
        Request $request,
        PublicApiException $exception,
    ): JsonResponse {
        return PublicApiResponse::error(
            $request,
            $exception->errorCode,
            $exception->getMessage(),
            $exception->httpStatus,
            $exception->fields,
        );
    }
}
