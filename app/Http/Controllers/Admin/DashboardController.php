<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\LemburQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        LemburQuery $lemburQuery,
        AdminDashboardService $dashboardService,
    ): Response {
        $filters = $lemburQuery->filters($request->query());

        return Inertia::render('admin/dashboard', [
            'summary' => $dashboardService->forMonth($filters['bulan']),
        ]);
    }
}
