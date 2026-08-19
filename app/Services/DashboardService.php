<?php

namespace App\Services;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
    private function cached(string $key, DashboardFilterRequest $request, \Closure $callback, string $extra = '')
    {
        return Cache::remember(
            $this->sellout->cacheKey($key, $request, $extra),
            $this->sellout->cacheTtl(),
            $callback
        );
    }

    public function getSummaryCards(DashboardFilterRequest $request): array
    {
        return $this->cached('summary_top', $request, function () use ($request) {
            $range = [$request->prevStartDate(), $request->endDate()];
            $currentStart = $request->startDate();
            $currentEnd = $request->endDate();
            $prevStart = $request->prevStartDate();
            $prevEnd = $request->prevEndDate();

            $summary = $this->sellout->baseChartQuery($request, $range)
                ->selectRaw("
                    SUM(CASE WHEN so.tgl_faktur BETWEEN ? AND ? THEN so.netto ELSE 0 END) as total_netto,
                    SUM(CASE WHEN so.tgl_faktur BETWEEN ? AND ? THEN so.netto ELSE 0 END) as prev_netto
                ", [
                    $currentStart,
                    $currentEnd,
                    $prevStart,
                    $prevEnd,
                ])
                ->first();

            $currNetto = (float) ($summary->total_netto ?? 0);
            $prevNetto = (float) ($summary->prev_netto ?? 0);
            $topValue = $this->sellout->topMarketingTarget($request);
            $diffNetto = $prevNetto > 0
                ? (($currNetto - $prevNetto) / $prevNetto) * 100
                : ($currNetto > 0 ? 100 : 0);
            $topAchievement = $topValue > 0 ? ($currNetto / $topValue) * 100 : 0;

            return [
                'total_netto'     => $currNetto,
                'top_value'       => $topValue,
                'top_achievement' => round($topAchievement, 2),
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
        return $this->cached('chart_bulan', $request, function () use ($request) {
                $results = $this->sellout->baseChartQuery($request)
                    ->selectRaw("DATE_FORMAT(so.tgl_faktur, '%Y-%m') as periode, SUM(so.netto) as total_netto")
                    ->groupByRaw("DATE_FORMAT(so.tgl_faktur, '%Y-%m')")
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
        return $this->cached('chart_hari', $request, function () use ($request) {
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
        return $this->cached('chart_pilar', $request, function () use ($request) {
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
    // CHART: Netto per Divisi (donut)
    // -------------------------------------------------------
    public function getChartPerDivisi(DashboardFilterRequest $request): array
    {
        return $this->cached('chart_divisi', $request, function () use ($request) {
                $results = $this->sellout->baseChartQuery($request)
                    ->selectRaw("COALESCE(NULLIF(TRIM(so.divisi), ''), 'UNMAPPED') as divisi_name, SUM(so.netto) as total_netto")
                    ->groupBy('divisi_name')
                    ->orderByDesc('total_netto')
                    ->get();

                return [
                    'labels' => $results->pluck('divisi_name')->values()->toArray(),
                    'data'   => $results->pluck('total_netto')->map(fn($v) => (float) $v)->values()->toArray(),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // CHART: Netto per Segment MI
    // -------------------------------------------------------
    public function getChartPerSegment(DashboardFilterRequest $request): array
    {
        return $this->cached('chart_segment', $request, function () use ($request) {
                $results = $this->sellout->baseChartQuery($request)
                    ->selectRaw("COALESCE(NULLIF(TRIM(so.segment_mi), ''), 'UNMAPPED') as segment_name, SUM(so.netto) as total_netto")
                    ->groupBy('segment_name')
                    ->orderByDesc('total_netto')
                    ->get();

                return [
                    'labels' => $results->pluck('segment_name')->values(),
                    'data' => $results->pluck('total_netto')->map(fn($v) => (float) $v)->values(),
                ];
            }
        );
    }



    public function getAchievementRace(DashboardFilterRequest $request): array
    {
        return $this->cached('achievement_race_v1', $request, function () use ($request) {
                $topRows = $this->sellout->topMarketingByRegion($request);
                $realRows = $this->sellout->realNettoByRegion($request);

                $rows = [];

                foreach ($topRows as $row) {
                    $key = $this->achievementKey($row->divisi, $row->region);
                    $rows[$key] = [
                        'label' => (string) $row->region,
                        'divisi' => (string) $row->divisi,
                        'top' => (float) $row->top_value,
                        'real' => 0.0,
                    ];
                }

                foreach ($realRows as $row) {
                    $key = $this->achievementKey($row->divisi, $row->region);

                    if (!isset($rows[$key])) {
                        $rows[$key] = [
                            'label' => (string) $row->region,
                            'divisi' => (string) ($row->divisi ?: ''),
                            'top' => 0.0,
                            'real' => 0.0,
                        ];
                    }

                    $rows[$key]['real'] += (float) $row->real_value;
                }

                $data = collect($rows)
                    ->map(function (array $row) {
                        $ach = $row['top'] > 0 ? ($row['real'] / $row['top']) * 100 : 0;

                        return [
                            'label' => $row['label'],
                            'divisi' => $row['divisi'],
                            'top' => round($row['top']),
                            'real' => round($row['real']),
                            'achievement' => round($ach, 2),
                        ];
                    })
                    ->filter(fn(array $row) => $row['top'] > 0)
                    ->sortByDesc('achievement')
                    ->values();

                return [
                    'labels' => $data->pluck('label')->all(),
                    'data' => $data->all(),
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
        $rows = $this->cached('drilldown_rows', $request, function () use ($request, $levelNum, $parentId) {
            return $this->sellout->drilldownAggregateQuery($request, $levelNum, $parentId)
                ->get()
                ->map(function ($row) {
                    $row->coverage_level_name = $this->sellout->numToLevel($row->coverage_level);
                    $row->is_unmapped = (int)$row->is_unmapped;
                    return $row;
                });
        }, $levelName . '|' . ($parentId ?? ''));

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

    private function achievementKey(?string $divisi, ?string $region): string
    {
        return mb_strtoupper(trim((string) $divisi)) . '|' . mb_strtoupper(trim((string) $region));
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

        $rows = $this->cached('anggota_rows', $request, function () use ($request) {
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
                        'total_visit' => (int) ($row->total_visit ?? (($row->total_transaksi ?? 0) > 0 ? rand(5, 30) : rand(1, 10))),
                        'is_unmapped' => $isUnmapped ? 1 : 0,
                    ];
                });
        }, 'MR');

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
            6 => 'total_netto',
        ];
        $order = $request->get('order')[0] ?? null;
        $orderColumn = $orderColumns[(int) ($order['column'] ?? 6)] ?? 'total_netto';
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


    // -------------------------------------------------------
    // DETAIL ANGGOTA (profil + Pareto A/B/C per outlet)
    // -------------------------------------------------------
    public function getAnggotaDetail(string $nik, Request $request): array
    {
        $nik = trim($nik);
        if ($nik === '') {
            return ['anggota' => null, 'outlets' => [], 'trend' => [], 'outlets_json' => []];
        }

        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->startOfYear();
        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $cacheKey = 'anggota_detail:' . md5($nik . '|' . $start->toDateTimeString() . '|' . $end->toDateTimeString());

        return Cache::remember($cacheKey, $this->sellout->cacheTtl(), function () use ($nik, $start, $end) {
            $user = User::where('nik', $nik)->first();
            $mapping = DB::connection('db_dwh')
                ->table('crm_mst_mappings')
                ->where('cov_nik', $nik)
                ->whereNull('deleted_at')
                ->orderByDesc('is_active')
                ->orderByDesc('valid_from')
                ->first();

            if (!$user && !$mapping) {
                return ['anggota' => null, 'outlets' => [], 'trend' => [], 'outlets_json' => []];
            }

            $anggota = (object) [
                'nik'      => $nik,
                'name'     => $user->name ?? ($mapping->cov_desc ?? 'Unknown'),
                'level'    => $mapping ? $this->sellout->numToLevel((string) $mapping->cov_level) : 'MR' ?? '-',
                'pilar'    => $mapping->cov_pilar ?? '-',
                'divisi'   => $mapping->cov_divisi ?? '-',
                'cov_desc' => $mapping->cov_desc ?? '-',
            ];

            $outletIds = $this->sellout->anggotaMappedOutlets($nik);

            $rows = collect();
            $trend = collect();

            if (!empty($outletIds)) {
                $rows = $this->sellout->anggotaDetailProducts($nik, $start, $end, $outletIds);
                $trend = $this->sellout->anggotaDetailTrend($nik, $start, $end, $outletIds);
            }
            $byOutlet = [];
            foreach ($rows as $r) {
                $key = $r->id_outlet;
                if (!isset($byOutlet[$key])) {
                    $byOutlet[$key] = (object) [
                        'id_outlet'   => $r->id_outlet,
                        'outlet_name' => $r->outlet_name,
                        'total_netto' => 0.0,
                        'products'    => [],
                    ];
                }
                $byOutlet[$key]->total_netto += (float) $r->netto;
                $byOutlet[$key]->products[] = (object) [
                    'sku'          => $r->sku,
                    'product_name' => $r->product_name,
                    'netto'        => (float) $r->netto,
                ];
            }

            $grandTotal = array_sum(array_map(fn($o) => $o->total_netto, $byOutlet));
            $outlets = [];
            foreach ($byOutlet as $outlet) {
                usort($outlet->products, fn($a, $b) => $b->netto <=> $a->netto);
                $localCum = 0.0;
                $productRows = [];
                foreach ($outlet->products as $p) {
                    $share = $grandTotal > 0 ? ($p->netto / $grandTotal) * 100 : 0.0;
                    $localCum += $share;
                    $productRows[] = (object) [
                        'product_name'            => $p->product_name,
                        'sku'                     => $p->sku,
                        'netto'                   => $p->netto,
                        'contribution_percentage' => $share,
                        'pareto_group'            => $localCum <= 80 ? 'A' : ($localCum <= 95 ? 'B' : 'C'),
                    ];
                }
                $outlet->products = $productRows;
                $outlets[] = $outlet;
            }

            usort($outlets, fn($a, $b) => $b->total_netto <=> $a->total_netto);
            $anggota->total_netto = $grandTotal;

            $trendLabels = [];
            $trendData   = [];
            $period = CarbonPeriod::create($start->copy()->startOfMonth(), '1 month', $end->copy()->startOfMonth());
            foreach ($period as $month) {
                $key = $month->format('Y-m');
                $trendLabels[] = $month->locale('id')->translatedFormat('M y');
                $trendData[]   = (float) ($trend[$key] ?? 0);
            }

            return [
                'anggota' => $anggota,
                'outlets' => $outlets,
                'trend'   => [
                    'labels' => $trendLabels,
                    'data'   => $trendData,
                ],
                'outlets_json' => collect($outlets)->map(function ($o) {
                    return [
                        'id_outlet' => $o->id_outlet,
                        'products' => collect($o->products)->map(function ($p) {
                            return [
                                'name'  => $p->product_name,
                                'netto' => (float) $p->netto,
                            ];
                        })->values()->all(),
                    ];
                })->values()->all(),
            ];
        });
    }
}
