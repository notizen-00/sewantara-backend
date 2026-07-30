<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Modules\TenantOnboarding\Application\Data\RegisterTenantCommand;
use App\Modules\TenantOnboarding\Application\RegisterTenant;
use App\Support\TenantHostname;
use DateTimeZone;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(
        RegisterTenantRequest $request,
        RegisterTenant $registerTenant,
    ): JsonResponse {
        $result = $registerTenant->execute(
            RegisterTenantCommand::fromArray($request->validated()),
        );

        return response()->json([
            'success' => true,
            'message' => 'Akun bisnis berhasil dibuat. Selesaikan onboarding untuk Go Live.',
            'data' => [
                'tenant' => [
                    'id' => $result->tenantId,
                    'name' => $result->tenantName,
                    'slug' => $result->tenantSlug,
                    'status' => $result->tenantStatus,
                    'timezone' => $result->timezone,
                    'currency' => $result->currency,
                ],
                'domain' => [
                    'domain' => $result->domain,
                    'url' => TenantHostname::url($result->domain),
                ],
                'owner' => [
                    'id' => $result->ownerId,
                    'name' => $result->ownerName,
                    'email' => $result->ownerEmail,
                ],
                'subscription' => [
                    'name' => 'main',
                    'plan' => $result->planSlug,
                    'status' => $result->subscriptionStatus,
                    'trial_ends_at' => $result->trialEndsAt
                        ?->setTimezone(new DateTimeZone($result->timezone))
                        ->format(DATE_ATOM),
                ],
            ],
            'meta' => null,
        ], 201);
    }
}
