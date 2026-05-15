<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    private DashboardService $service;

    public function __construct(DashboardService $service)
    {
        $this->middleware('auth');
        $this->service = $service;
    }

    // -------------------------------------------------------
    // Render halaman dashboard
    // -------------------------------------------------------
    public function index()
    {
        return view('page.dashboard.index', [
            'levels' => ['GM', 'RM', 'SAM', 'AM', 'MR'],
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
        return response()->json($this->service->getDrillDown($request));
    }

    public function getTableAnggota(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getTableAnggota($request));
    }

    public function getTableOutlet(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getTableOutlet($request));
    }
}
