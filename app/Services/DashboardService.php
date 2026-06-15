<?php

namespace App\Services;

use App\Http\Requests\DashboardFilterRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    private $sellout;
    private CoverageAccessService $coverageAccess;

    public function __construct(SelloutQueryService $sellout, CoverageAccessService $coverageAccess)
    {
        $this->sellout = $sellout;
        $this->coverageAccess = $coverageAccess;
    }

    // -------------------------------------------------------
    // SUMMARY CARDS
    // -------------------------------------------------------

    // Contoh pada getSummaryCards
    public function getSummaryCards(DashboardFilterRequest $request): array
    {
        $cacheKey = $this->sellout->cacheKey('summary', $request);

        return Cache::remember($cacheKey, $this->sellout->cacheTtl(), function () use ($request) {
            $range = [$request->prevStartDate(), $request->endDate()];
            $currentStart = $request->startDate();
            $currentEnd = $request->endDate();
            $prevStart = $request->prevStartDate();
            $prevEnd = $request->prevEndDate();

            $summary = $this->sellout->baseChartQuery($request, $range)
                ->selectRaw("
                    SUM(CASE WHEN so.tgl_faktur BETWEEN ? AND ? THEN so.netto ELSE 0 END) as total_netto,
                    COUNT(DISTINCT CASE WHEN so.tgl_faktur BETWEEN ? AND ? THEN so.id_outlet END) as total_outlet,
                    COUNT(DISTINCT CASE WHEN so.tgl_faktur BETWEEN ? AND ? THEN so.coverage_nik END) as total_anggota,
                    COUNT(DISTINCT CASE WHEN so.tgl_faktur BETWEEN ? AND ? THEN so.source_id END) as total_transaksi,
                    SUM(CASE WHEN so.tgl_faktur BETWEEN ? AND ? THEN so.netto ELSE 0 END) as prev_netto
                ", [
                    $currentStart,
                    $currentEnd,
                    $currentStart,
                    $currentEnd,
                    $currentStart,
                    $currentEnd,
                    $currentStart,
                    $currentEnd,
                    $prevStart,
                    $prevEnd,
                ])
                ->first();

            $currNetto = (float) ($summary->total_netto ?? 0);
            $prevNetto = (float) ($summary->prev_netto ?? 0);
            $diffNetto = $prevNetto > 0
                ? (($currNetto - $prevNetto) / $prevNetto) * 100
                : ($currNetto > 0 ? 100 : 0);

            return [
                'total_netto'     => $currNetto,
                'total_outlet'    => (int) ($summary->total_outlet ?? 0),
                'total_anggota'   => (int) ($summary->total_anggota ?? 0),
                'total_transaksi' => (int) ($summary->total_transaksi ?? 0),
                'prev_netto'      => $prevNetto,
                'diff_netto'      => round($diffNetto, 2)
            ];
        });
    }

    // -------------------------------------------------------
    // CHART: Netto per Bulan (area/line)
    // -------------------------------------------------------
    public function getChartPerBulan(DashboardFilterRequest $request): array
    {
        return Cache::remember(
            $this->sellout->cacheKey('chart_bulan', $request),
            $this->sellout->cacheTtl(),
            function () use ($request) {
                $results = $this->sellout->monthlyChartQuery($request)
                    ->get()
                    ->keyBy('periode');

                $labels = [];
                $data   = [];

                $period = CarbonPeriod::create(
                    Carbon::parse($request->startDate())->startOfMonth(),
                    '1 month',
                    Carbon::parse($request->endDate())->startOfMonth()
                );

                foreach ($period as $month) {
                    $key = $month->format('Y-m');
                    $labels[] = $month->locale('id')->translatedFormat('M Y');
                    $data[] = $results->has($key) ? round((float) $results[$key]->total_netto) : 0;
                }

                return [
                    'labels' => $labels,
                    'data' => $data,
                    'mode' => 'bulan',
                    'period' => $this->periodLabel($request),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // CHART: Netto per Hari (area/line)
    // -------------------------------------------------------
    public function getChartPerHari(DashboardFilterRequest $request): array
    {
        return Cache::remember(
            $this->sellout->cacheKey('chart_hari', $request),
            $this->sellout->cacheTtl(),
            function () use ($request) {
                // Gunakan baseQuery (bukan clone manual)
                $results = $this->sellout->baseChartQuery($request)
                    ->selectRaw('DATE(so.tgl_faktur) as tanggal, SUM(so.netto) as total_netto')
                    ->groupByRaw('DATE(so.tgl_faktur)')
                    ->get()
                    ->keyBy('tanggal');

                $labels    = [];
                $data      = [];

                $period = CarbonPeriod::create(
                    Carbon::parse($request->startDate())->startOfDay(),
                    Carbon::parse($request->endDate())->startOfDay()
                );

                foreach ($period as $date) {
                    $key = $date->toDateString();
                    $labels[] = $date->locale('id')->translatedFormat('d M');
                    $data[] = $results->has($key) ? round((float) $results[$key]->total_netto) : 0;
                }

                return [
                    'labels' => $labels,
                    'data'   => $data,
                    'mode'   => 'hari',
                    'period' => $this->periodLabel($request),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // CHART: Netto per Pilar (bar chart — bukan donut)
    // -------------------------------------------------------
    public function getChartPerPilar(DashboardFilterRequest $request): array
    {
        return Cache::remember(
            $this->sellout->cacheKey('chart_pilar', $request),
            $this->sellout->cacheTtl(),
            function () use ($request) {
                $results = $this->sellout->baseChartQuery($request)
                    // GUNAKAN product_pilar (dari produk) + COALESCE agar data unmapped masuk ke "NO PILAR"
                    ->selectRaw('COALESCE(so.product_pilar, "UNMAPPED") as pilar_name, SUM(so.netto) as total_netto')
                    ->groupBy('pilar_name')
                    ->get();

                return [
                    'labels' => $results->pluck('pilar_name'),
                    'data'   => $results->pluck('total_netto')->map(fn($v) => (float)$v),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // CHART: Netto per Divisi (donut — Reguler vs CMA)
    // -------------------------------------------------------
    public function getChartPerDivisi(DashboardFilterRequest $request): array
    {
        return Cache::remember(
            $this->sellout->cacheKey('chart_divisi', $request),
            $this->sellout->cacheTtl(),
            function () use ($request) {
                $results = $this->sellout->drilldownAggregateQuery(
                    $request,
                    $this->sellout->levelToNum('GM')
                )->get();

                $totals = $results->groupBy(function ($row) {
                    if ((int) $row->is_unmapped === 1 || $row->coverage_id === 'UNMAPPED') {
                        return 'Belum Mapping';
                    }

                    if ($row->coverage_divisi === 'Reguler') {
                        return 'Reguler';
                    }

                    if ($row->coverage_divisi === null || $row->coverage_divisi === '') {
                        return 'Divisi Kosong';
                    }

                    return 'CMA';
                })->map(fn($rows) => $rows->sum(fn($row) => (float) $row->total_netto))
                    ->sortDesc();

                return [
                    'labels' => $totals->keys()->values()->toArray(),
                    'data'   => $totals->values()->toArray(),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // CHART: Top 10 Coverage by Netto (bar horizontal)
    // -------------------------------------------------------
    public function getChartPerCoverage(DashboardFilterRequest $request): array
    {
        return Cache::remember(
            $this->sellout->cacheKey('chart_coverage', $request),
            $this->sellout->cacheTtl(),
            function () use ($request) {
                $results = (clone $this->sellout->baseChartQuery($request))
                    ->selectRaw('so.coverage_id, so.coverage_desc, so.coverage_level, SUM(so.netto) as total_netto')
                    ->groupBy('so.coverage_id', 'so.coverage_desc', 'so.coverage_level')
                    ->orderByDesc('total_netto')
                    ->limit(10)
                    ->get();

                return [
                    'labels' => $results->map(fn($r) => $r->coverage_desc ?: $r->coverage_id)->values(),
                    'data'   => $results->pluck('total_netto')->map(fn($v) => (float)$v)->values(),
                    'levels' => $results->pluck('coverage_level')->map(fn($v) => $this->sellout->numToLevel($v))->values(),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // DRILL-DOWN
    // -------------------------------------------------------
    public function getDrillDown(DashboardFilterRequest $request): array
    {
        $draw   = (int) $request->get('draw', 0);
        $start  = max((int) $request->get('start', 0), 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? '';

        $accessConfig = $this->coverageAccess->dashboardAccessConfig(auth()->user());
        $allowedLevels = $accessConfig['drilldown_levels'] ?? [];
        $levelName  = $request->get('level', 'GM');
        if (!empty($allowedLevels) && !in_array($levelName, $allowedLevels, true)) {
            $levelName = $allowedLevels[0];
        }
        $parentId   = $request->get('coverage_id');
        $levelNum   = $this->sellout->levelToNum($levelName);

        $nextLevel = $this->sellout->nextLevel($levelName);
        $cacheKey = $this->sellout->cacheKey('drilldown_rows', $request, $levelName . '|' . ($parentId ?? ''));
        $rows = Cache::remember($cacheKey, $this->sellout->cacheTtl(), function () use ($request, $levelNum, $parentId) {
            return $this->sellout->drilldownAggregateQuery($request, $levelNum, $parentId)
                ->get()
                ->map(function ($row) {
                    $row->coverage_level_name = $this->sellout->numToLevel($row->coverage_level);
                    $row->is_unmapped = (int)$row->is_unmapped;
                    return $row;
                });
        });

        if ($draw > 0) {
            $recordsTotal = $rows->count();
            $filteredRows = $rows;

            if ($search !== '') {
                $needle = mb_strtolower($search);
                $filteredRows = $rows->filter(function ($row) use ($needle) {
                    return str_contains(mb_strtolower((string) $row->coverage_id), $needle)
                        || str_contains(mb_strtolower((string) $row->coverage_desc), $needle)
                        || str_contains(mb_strtolower((string) $row->coverage_nik), $needle)
                        || str_contains(mb_strtolower((string) $row->user_name), $needle)
                        || str_contains(mb_strtolower((string) $row->coverage_pilar), $needle);
                });
            }

            $recordsFiltered = $filteredRows->count();
            $orderColumns = [
                1 => 'coverage_id',
                2 => 'coverage_desc',
                3 => 'coverage_nik',
                4 => 'user_name',
                5 => 'coverage_pilar',
                6 => 'total_outlet',
                7 => 'total_transaksi',
                8 => 'total_netto',
            ];
            $order = $request->get('order')[0] ?? null;
            $orderColumn = $orderColumns[(int) ($order['column'] ?? 8)] ?? 'total_netto';
            $orderDir = strtolower($order['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

            $numericOrderColumns = ['total_outlet', 'total_transaksi', 'total_netto'];
            $sortValue = function ($row) use ($orderColumn, $numericOrderColumns) {
                $value = $row->{$orderColumn} ?? null;

                return in_array($orderColumn, $numericOrderColumns, true)
                    ? (float) $value
                    : mb_strtolower((string) $value);
            };
            $sortedRows = $orderDir === 'asc'
                ? $filteredRows->sortBy($sortValue)
                : $filteredRows->sortByDesc($sortValue);
            $results = $length > 0
                ? $sortedRows->slice($start, $length)->values()
                : $sortedRows->values();

            return [
                'draw'            => $draw,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data'            => $results,
                'level'           => $levelName,
                'level_num'       => $levelNum,
                'next_level'      => $nextLevel,
                'parent_id'       => $parentId,
            ];
        }

        return [
            'level'      => $levelName,
            'level_num'  => $levelNum,
            'next_level' => $nextLevel,
            'parent_id'  => $parentId,
            'data'       => $rows,
        ];
    }

    private function periodLabel(DashboardFilterRequest $request): string
    {
        $start = Carbon::parse($request->startDate())->locale('id')->translatedFormat('d M Y');
        $end = Carbon::parse($request->endDate())->locale('id')->translatedFormat('d M Y');

        return $start . ' - ' . $end;
    }

    // -------------------------------------------------------
    // TABEL: Per Anggota (DataTables server-side)
    // -------------------------------------------------------
    public function getTableAnggota(DashboardFilterRequest $request): array
    {
        $draw   = (int) $request->get('draw', 1);
        $start  = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? '';

        $cacheKey = $this->sellout->cacheKey('anggota_rows', $request, 'MR');
        $rows = Cache::remember($cacheKey, $this->sellout->cacheTtl(), function () use ($request) {
            return $this->sellout->drilldownAggregateQuery($request, $this->sellout->levelToNum('MR'))
                ->get()
                ->map(function ($row) {
                    $isUnmapped = (int) $row->is_unmapped === 1 || $row->coverage_id === 'UNMAPPED';

                    return (object) [
                        'nik' => $isUnmapped || $row->coverage_nik === '-' ? null : $row->coverage_nik,
                        'name' => $isUnmapped || $row->user_name === 'N/A' ? null : $row->user_name,
                        'level' => $row->coverage_level,
                        'level_name' => $this->sellout->numToLevel($row->coverage_level),
                        'pilar' => $row->coverage_pilar,
                        'divisi' => $row->coverage_divisi,
                        'coverage_id' => $row->coverage_id,
                        'cov_desc' => $row->coverage_desc,
                        'total_netto' => (float) $row->total_netto,
                        'total_outlet' => (int) $row->total_outlet,
                        'total_transaksi' => (int) $row->total_transaksi,
                        'is_unmapped' => $isUnmapped ? 1 : 0,
                    ];
                });
        });

        $total = $rows->count();
        $filteredRows = $rows;

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $filteredRows = $rows->filter(function ($row) use ($needle) {
                return str_contains(mb_strtolower((string) $row->nik), $needle)
                    || str_contains(mb_strtolower((string) $row->name), $needle)
                    || str_contains(mb_strtolower((string) $row->coverage_id), $needle)
                    || str_contains(mb_strtolower((string) $row->cov_desc), $needle)
                    || str_contains(mb_strtolower((string) $row->pilar), $needle)
                    || str_contains(mb_strtolower((string) $row->level_name), $needle);
            });
        }

        $filtered = $filteredRows->count();
        $orderColumns = [
            1 => 'nik',
            2 => 'name',
            3 => 'level_name',
            4 => 'pilar',
            5 => 'divisi',
            6 => 'cov_desc',
            7 => 'total_outlet',
            8 => 'total_transaksi',
            9 => 'total_netto',
        ];
        $order = $request->get('order')[0] ?? null;
        $orderColumn = $orderColumns[(int) ($order['column'] ?? 9)] ?? 'total_netto';
        $orderDir = strtolower($order['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $numericOrderColumns = ['total_outlet', 'total_transaksi', 'total_netto'];

        $sortValue = function ($row) use ($orderColumn, $numericOrderColumns) {
            $value = $row->{$orderColumn} ?? null;

            return in_array($orderColumn, $numericOrderColumns, true)
                ? (float) $value
                : mb_strtolower((string) $value);
        };

        $records = ($orderDir === 'asc'
                ? $filteredRows->sortBy($sortValue)
                : $filteredRows->sortByDesc($sortValue))
            ->slice($start, $length)
            ->values();

        return [
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $records,
        ];

    }
}
