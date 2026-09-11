<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminDashboardResource;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\LemburQuery;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        LemburQuery $lemburQuery,
        AdminDashboardService $dashboardService,
    ): AdminDashboardResource {
        $filters = $lemburQuery->filters($request->query());

        return new AdminDashboardResource($dashboardService->forMonth($filters['bulan']));
    }
}
