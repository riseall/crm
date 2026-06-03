<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Services\CoverageAccessService;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private DashboardService $service;
    private CoverageAccessService $coverageAccess;

    public function __construct(DashboardService $service, CoverageAccessService $coverageAccess)
    {
        $this->middleware('auth');
        $this->service = $service;
        $this->coverageAccess = $coverageAccess;
    }

    // -------------------------------------------------------
    // Render halaman dashboard
    // -------------------------------------------------------
    public function index()
    {
        $user = Auth::user();
        $dashboardAccess = $this->coverageAccess->dashboardAccessConfig($user);

        return view('page.dashboard.index', [
            'dashboardAccess' => $dashboardAccess,
            'drilldownLevels' => $dashboardAccess['drilldown_levels'],
            'drilldownDefaultLevel' => $dashboardAccess['default_drilldown_level'],
            'pilars' => ['ETHICAL', 'OGB', 'OTC'],
            'months' => collect(range(1, 12))
                ->mapWithKeys(fn($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])
                ->toArray(),
        ]);
    }

    // -------------------------------------------------------
    // API Endpoints — semua return JSON
    // -------------------------------------------------------
    public function getSummaryCards(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getSummaryCards($request));
    }

    public function getChartPerBulan(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getChartPerBulan($request));
    }

    public function getChartPerHari(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getChartPerHari($request));
    }

    public function getChartPerPilar(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getChartPerPilar($request));
    }

    public function getChartPerDivisi(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getChartPerDivisi($request));
    }

    public function getChartPerCoverage(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getChartPerCoverage($request));
    }

    public function getDrillDown(DashboardFilterRequest $request)
    {
        abort_unless($this->coverageAccess->canViewDashboardDrilldown(Auth::user()), 403);

        return response()->json($this->service->getDrillDown($request));
    }

    public function getTableAnggota(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getTableAnggota($request));
    }
}
