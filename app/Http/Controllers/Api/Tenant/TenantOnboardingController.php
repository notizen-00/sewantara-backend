<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\RentalEngine\Domain\AllocationStrategy;
use App\Modules\RentalEngine\Domain\BookingStrategy;
use App\Modules\RentalEngine\Domain\RentalModel;
use App\Modules\TenantOnboarding\Application\ConfigureTenantOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantOnboardingController extends Controller
{
    public function show(ConfigureTenantOnboarding $onboarding): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $onboarding->snapshot($this->tenantId()),
        ]);
    }

    public function business(
        Request $request,
        ConfigureTenantOnboarding $onboarding,
    ): JsonResponse {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3'],
            'branch_name' => ['required', 'string', 'max:150'],
            'operating_hours' => ['required', 'array'],
        ]);

        return $this->updated(
            'Informasi usaha berhasil diperbarui.',
            $onboarding->business($this->tenantId(), $validated),
        );
    }

    public function rental(
        Request $request,
        ConfigureTenantOnboarding $onboarding,
    ): JsonResponse {
        $validated = $request->validate([
            'rental_model' => ['required', Rule::enum(RentalModel::class)],
            'booking_strategy' => ['required', Rule::enum(BookingStrategy::class)],
            'allocation_strategy' => ['required', Rule::enum(AllocationStrategy::class)],
            'slot_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
            'enable_waiting_list' => ['required', 'boolean'],
            'allow_extend_booking' => ['required', 'boolean'],
            'realtime_availability' => ['required', 'boolean'],
        ]);

        return $this->updated(
            'Konfigurasi penyewaan berhasil diperbarui.',
            $onboarding->rental($this->tenantId(), $validated),
        );
    }

    public function inventory(ConfigureTenantOnboarding $onboarding): JsonResponse
    {
        return $this->updated(
            'Pengaturan persediaan telah berhasil diverifikasi.',
            $onboarding->inventoryCompleted($this->tenantId()),
        );
    }

    public function pricing(ConfigureTenantOnboarding $onboarding): JsonResponse
    {
        return $this->updated(
            'Pengaturan harga telah berhasil diverifikasi.',
            $onboarding->pricingCompleted($this->tenantId()),
        );
    }

    public function booking(
        Request $request,
        ConfigureTenantOnboarding $onboarding,
    ): JsonResponse {
        $validated = $request->validate([
            'allow_online_booking' => ['required', 'boolean'],
            'allow_walk_in' => ['required', 'boolean'],
            'enable_waiting_list' => ['required', 'boolean'],
            'allocation_strategy' => ['required', Rule::enum(AllocationStrategy::class)],
            'auto_reminder' => ['required', 'boolean'],
            'auto_cancel_unpaid' => ['required', 'boolean'],
            'auto_cancel_minutes' => [
                'nullable',
                'integer',
                'min:5',
                'required_if:auto_cancel_unpaid,true',
            ],
        ]);

        return $this->updated(
            'Konfigurasi pemesanan berhasil diperbarui.',
            $onboarding->booking($this->tenantId(), $validated),
        );
    }

    public function payments(
        Request $request,
        ConfigureTenantOnboarding $onboarding,
    ): JsonResponse {
        $validated = $request->validate([
            'methods' => ['required', 'array', 'min:1'],
            'methods.*.method' => [
                'required',
                Rule::in(['cash', 'transfer', 'midtrans', 'deposit', 'pay_later']),
                'distinct',
            ],
            'methods.*.is_enabled' => ['required', 'boolean'],
            'methods.*.configuration' => ['nullable', 'array'],
        ]);

        return $this->updated(
            'Konfigurasi pembayaran berhasil diperbarui.',
            $onboarding->payments($this->tenantId(), $validated['methods']),
        );
    }

    public function goLive(ConfigureTenantOnboarding $onboarding): JsonResponse
    {
        return $this->updated(
            'Penyiapan awal selesai. Akun usaha kini aktif dan siap digunakan.',
            $onboarding->goLive($this->tenantId()),
        );
    }

    private function tenantId(): string
    {
        return (string) app('currentTenant')->getTenantKey();
    }

    private function updated(string $message, array $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
