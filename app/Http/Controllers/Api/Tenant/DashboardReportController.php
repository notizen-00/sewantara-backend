<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Application\GetDashboardReport;

class DashboardReportController extends Controller
{
    public function __invoke(GetDashboardReport $dashboardReport)
    {
        return response()->json([
            'success' => true,
            'data' => $dashboardReport->execute(app('currentTenant')),
        ]);
    }
}
