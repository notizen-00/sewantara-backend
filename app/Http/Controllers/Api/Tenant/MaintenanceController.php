<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use App\Modules\Inventory\Application\ManageMaintenance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    public function index(Request $request, ManageMaintenance $maintenance)
    {
        return response()->json([
            'success' => true,
            'data' => $maintenance->paginate(
                $request->string('status')->toString() ?: null,
                $request->integer('product_unit_id') ?: null,
                $request->integer('per_page', 20),
            ),
        ]);
    }

    public function store(Request $request, ManageMaintenance $maintenance)
    {
        $validated = $request->validate([
            'product_unit_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in(['service', 'repair', 'cleaning', 'inspection', 'calibration'])],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pemeliharaan berhasil dijadwalkan.',
            'data' => $maintenance->create(
                app('currentTenant')->id,
                $request->user()?->id,
                $validated,
            ),
        ], 201);
    }

    public function show(
        MaintenanceRecord $maintenance,
        ManageMaintenance $manager,
    ) {
        return response()->json([
            'success' => true,
            'data' => $manager->detail($maintenance),
        ]);
    }

    public function start(
        MaintenanceRecord $maintenance,
        Request $request,
        ManageMaintenance $manager,
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Pemeliharaan dimulai dan unit tidak dapat dipesan untuk sementara.',
            'data' => $manager->start($maintenance, $request->user()?->id),
        ]);
    }

    public function complete(
        MaintenanceRecord $maintenance,
        Request $request,
        ManageMaintenance $manager,
    ) {
        $validated = $request->validate([
            'unit_status' => ['nullable', Rule::in(['available', 'damaged', 'inactive'])],
            'condition' => ['nullable', 'string', 'max:30'],
            'current_meter' => ['nullable', 'integer', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pemeliharaan selesai dan status unit telah diperbarui.',
            'data' => $manager->complete(
                $maintenance,
                $request->user()?->id,
                $validated,
            ),
        ]);
    }

    public function cancel(
        MaintenanceRecord $maintenance,
        Request $request,
        ManageMaintenance $manager,
    ) {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pemeliharaan dibatalkan.',
            'data' => $manager->cancel(
                $maintenance,
                $request->user()?->id,
                $validated['notes'] ?? null,
            ),
        ]);
    }
}
