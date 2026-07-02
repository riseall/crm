<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockFilterRequest;
use App\Services\StockService;
use Illuminate\Support\Facades\Cache;

class StockController extends Controller
{
    private StockService $service;

    public function __construct(StockService $service)
    {
        $this->middleware('auth');
        $this->service = $service;
    }

    public function index()
    {
        return view('page.dashboard.stock');
    }

    public function getDashboard(StockFilterRequest $request)
    {
        $cacheKey = $this->service->getQueryService()->cacheKey('dashboard', $request);
        $payload = Cache::remember($cacheKey, $this->service->getQueryService()->cacheTtl(), function () use ($request) {
            return $this->service->getDashboardData($request);
        });

        return response()->json($payload);
    }

    public function getTableDetail(StockFilterRequest $request)
    {
        return response()->json($this->service->getTableDetail($request));
    }

    public function getFilterOptions()
    {
        return response()->json($this->service->getFilterOptions());
    }
}
