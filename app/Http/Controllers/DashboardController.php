<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Services\CoverageAccessService;
use App\Services\DashboardService;
use App\Services\SelloutQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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
        $dashboardAccess['user_role_context'] = $this->service->getUserRoleContext($user);

        return view('page.dashboard.index', [
            'dashboardAccess' => $dashboardAccess,
            'drilldownLevels' => $dashboardAccess['drilldown_levels'],
            'drilldownDefaultLevel' => $dashboardAccess['default_drilldown_level'],
            'pilars' => ['ETHIPROM', 'ETHICOM', 'OGB', 'OTC'],
            'months' => collect(range(1, 12))
                ->mapWithKeys(fn($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])
                ->toArray(),
        ]);
    }

    // -------------------------------------------------------
    // API Endpoints — semua return JSON
    // -------------------------------------------------------
    public function getConsolidatedData(DashboardFilterRequest $request, SelloutQueryService $selloutQuery)
    {
        $cacheKey = $selloutQuery->cacheKey('consolidated_data', $request);

        $data = Cache::remember($cacheKey, $selloutQuery->cacheTtl(), function () use ($request) {
            return [
                'summary'          => $this->service->getSummaryCards($request),
                'chart_hari'       => $this->service->getChartPerHari($request),
                'chart_bulan'      => null,
                'chart_pilar'      => $this->service->getChartPerPilar($request),
                'chart_divisi'     => $this->service->getChartPerDivisi($request),
                'chart_segment'    => $this->service->getChartPerSegment($request),
                'achievement_race' => $this->service->getAchievementRace($request),
            ];
        });

        return response()->json($data);
    }

    public function getChartPerBulan(DashboardFilterRequest $request, SelloutQueryService $selloutQuery)
    {
        $cacheKey = $selloutQuery->annualCacheKey('chart_per_bulan', $request);

        $data = Cache::remember($cacheKey, 1800, function () use ($request) {
            return $this->service->getChartPerBulan($request);
        });

        return response()->json($data);
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

    public function getChartRace(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getAchievementRace($request, $request->get('race_mode')));
    }

    public function getTableAchievement(DashboardFilterRequest $request)
    {
        return response()->json($this->service->getTableAchievement($request));
    }

    public function detailAnggota(Request $request, $nik)
    {
        abort_unless($this->coverageAccess->canViewDashboardDrilldown(Auth::user()) ?? true, 403);

        $data = $this->service->getAnggotaDetail((string) $nik, $request);

        if (!$data['anggota']) {
            abort(404, 'Data anggota dengan NIK ' . $nik . ' tidak ditemukan.');
        }

        return view('page.dashboard.partials.detail-anggota', [
            'anggota'      => $data['anggota'],
            'outlets'      => $data['outlets'],
            'trend'        => $data['trend'],
            'outletsJson'  => json_encode($data['outlets_json']),
        ]);
    }
}
