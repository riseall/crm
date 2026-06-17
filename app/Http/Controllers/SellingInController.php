<?php

namespace App\Http\Controllers;

use App\Http\Requests\SellingInFilterRequest;
use App\Services\SellingInQueryService;
use App\Services\SellingInService;
use Illuminate\Http\Request;

/**
 * SellingInController
 *
 * Controller tipis — hanya routing request ke service dan return JSON.
 * Tidak ada business logic di sini.
 */
class SellingInController extends Controller
{
    private SellingInService $service;
    private SellingInQueryService $queryService;

    public function __construct(SellingInService $service, SellingInQueryService $queryService)
    {
        $this->middleware('auth');
        $this->service = $service;
        $this->queryService = $queryService;
    }

    // -------------------------------------------------------
    // View
    // -------------------------------------------------------

    public function index()
    {
        return view('page.dashboard.sell_in', [
            'pilars'       => ['ETHIPROM', 'ETHICOM', 'OGB', 'OTC', 'EXPORT'],
            'months'       => collect(range(1, 12))
                ->mapWithKeys(fn($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])
                ->toArray(),
            'years' => collect(range(now()->year - 3, now()->year))
                ->reverse()->values()->toArray(),
        ]);
    }

    // -------------------------------------------------------
    // API Endpoints
    // -------------------------------------------------------

    public function getSummaryCards(SellingInFilterRequest $request)
    {
        return response()->json($this->service->getSummaryCards($request));
    }

    public function getChartPerHari(SellingInFilterRequest $request)
    {
        return response()->json($this->service->getChartPerHari($request));
    }

    public function getChartPerBulan(SellingInFilterRequest $request)
    {
        return response()->json($this->service->getChartPerBulan($request));
    }

    public function getChartPerPilar(SellingInFilterRequest $request)
    {
        return response()->json($this->service->getChartPerPilar($request));
    }

    public function getTopPm(SellingInFilterRequest $request)
    {
        return response()->json($this->service->getTopPm($request));
    }

    public function getTopProduk(SellingInFilterRequest $request)
    {
        return response()->json($this->service->getTopProduk($request));
    }

    public function getTableDetail(SellingInFilterRequest $request)
    {
        return response()->json($this->service->getTableDetail($request));
    }

    public function getPmLookup(Request $request)
    {
        $results = $this->queryService->pmLookup(
            (string) $request->query('q', ''),
            30
        );

        return response()->json(['results' => $results]);
    }
}
