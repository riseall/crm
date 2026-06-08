<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockFilterRequest;
use App\Services\StockService;

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

    public function getSummaryCards(StockFilterRequest $request)
    {
        return response()->json($this->service->getSummaryCards($request));
    }

    public function getChartAging(StockFilterRequest $request)
    {
        return response()->json($this->service->getChartAging($request));
    }

    public function getChartAgingPerSource(StockFilterRequest $request)
    {
        return response()->json($this->service->getChartAgingPerSource($request));
    }

    public function getPerSource(StockFilterRequest $request)
    {
        return response()->json($this->service->getPerSource($request));
    }

    public function getTopCabang(StockFilterRequest $request)
    {
        return response()->json($this->service->getTopCabang($request));
    }

    public function getProdukPrioritas(StockFilterRequest $request)
    {
        return response()->json($this->service->getProdukPrioritas($request));
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
