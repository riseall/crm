<?php

namespace App\Services;

use App\Http\Requests\StockFilterRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StockService
{
    private StockQueryService $query;

    public function __construct(StockQueryService $query)
    {
        $this->query = $query;
    }

    public function getQueryService(): StockQueryService
    {
        return $this->query;
    }

    public function getDashboardData(StockFilterRequest $request): array
    {
        return [
            'summary' => $this->getSummaryCards($request),
            'chart_aging' => $this->getChartAging($request),
            'per_source' => $this->getPerSource($request),
            'top_cabang' => $this->getTopCabang($request),
        ];
    }

    // -------------------------------------------------------
    // SUMMARY WARNING
    // -------------------------------------------------------

    public function getSummaryCards(StockFilterRequest $request): array
    {
        return Cache::remember(
            $this->query->cacheKey('summary_warning_v2', $request),
            $this->query->cacheTtl(),
            function () use ($request) {
                $rows = $this->query->baseQuery($request)
                    ->whereIn('s.kelompok_ed', ['1_3', '4_7', '8_12'])
                    ->selectRaw('
                        s.kelompok_ed,
                        SUM(s.stock) as total_stock,
                        SUM(s.value) as total_value,
                        COUNT(DISTINCT s.pt_ph) as total_sku
                    ')
                    ->groupBy('s.kelompok_ed')
                    ->get()
                    ->keyBy('kelompok_ed');

                $labels = [
                    '1_3' => '1-3 bulan',
                    '4_7' => '4-7 bulan',
                    '8_12' => '8-12 bulan',
                ];

                return [
                    'warnings' => collect($labels)->map(function ($label, $key) use ($rows) {
                        $row = $rows->get($key);

                        return [
                            'key' => $key,
                            'label' => $label,
                            'total_stock' => round((float) ($row->total_stock ?? 0), 2),
                            'total_value' => round((float) ($row->total_value ?? 0)),
                            'total_sku' => (int) ($row->total_sku ?? 0),
                        ];
                    })->values(),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // CHART: Donut aging keseluruhan
    // -------------------------------------------------------

    public function getChartAging(StockFilterRequest $request): array
    {
        return Cache::remember(
            $this->query->cacheKey('chart_aging', $request),
            $this->query->cacheTtl(),
            function () use ($request) {
                $results = $this->query->baseQuery($request)
                    ->selectRaw('s.kelompok_ed, SUM(s.value) as total_value, COUNT(DISTINCT s.pt_ph) as total_sku')
                    ->groupBy('s.kelompok_ed')
                    ->get()
                    ->keyBy('kelompok_ed');

                $order  = StockQueryService::kelompokEdOrder();
                $total  = $results->sum('total_value') ?: 1;

                $labels = $values = $skus = $pcts = [];

                foreach ($order as $key) {
                    $row      = $results->get($key);
                    $val      = (float) ($row->total_value ?? 0);
                    $labels[] = StockQueryService::kelompokEdLabel($key);
                    $values[] = round($val);
                    $skus[]   = (int) ($row->total_sku ?? 0);
                    $pcts[]   = round(($val / $total) * 100, 1);
                }

                return compact('labels', 'values', 'skus', 'pcts');
            }
        );
    }

    // -------------------------------------------------------
    // Per source (ringkasan kartu distributor)
    // -------------------------------------------------------

    public function getPerSource(StockFilterRequest $request): array
    {
        return Cache::remember(
            $this->query->cacheKey('per_source', $request),
            $this->query->cacheTtl(),
            function () use ($request) {
                $total = (float) ($this->query->baseQuery($request)->sum('s.value') ?: 1);

                return $this->query->baseQuery($request)
                    ->selectRaw('s.source, SUM(s.value) as total_value, SUM(s.stock) as total_stock, COUNT(DISTINCT s.pt_ph) as total_sku')
                    ->groupBy('s.source')
                    ->orderByDesc('total_value')
                    ->get()
                    ->map(fn($r) => [
                        'source'      => $r->source,
                        'total_value' => round((float) $r->total_value),
                        'total_stock' => round((float) $r->total_stock),
                        'total_sku'   => (int) $r->total_sku,
                        'pct'         => round(($r->total_value / $total) * 100, 1),
                    ])
                    ->values()->toArray();
            }
        );
    }

    // -------------------------------------------------------
    // Top cabang by nilai
    // -------------------------------------------------------

    public function getTopCabang(StockFilterRequest $request, int $limit = 10): array
    {
        return Cache::remember(
            $this->query->cacheKey('top_cabang', $request, (string) $limit),
            $this->query->cacheTtl(),
            function () use ($request, $limit) {
                $results = $this->query->baseQuery($request)
                    ->whereIn('s.source', ['AAM', 'KFTD', 'METD'])
                    ->selectRaw('s.cabang, GROUP_CONCAT(DISTINCT s.source ORDER BY s.source SEPARATOR ", ") as sources, SUM(s.value) as total_value')
                    ->groupBy('s.cabang')
                    ->orderByDesc('total_value')
                    ->limit($limit)
                    ->get();

                $max = $results->max('total_value') ?: 1;

                return $results->map(fn($r) => [
                    'cabang'      => $r->cabang,
                    'sources'     => $r->sources,
                    'total_value' => round((float) $r->total_value),
                    'pct_of_max'  => round(($r->total_value / $max) * 100, 1),
                ])->values()->toArray();
            }
        );
    }

    // -------------------------------------------------------
    // TABEL DETAIL — DataTables server-side
    // -------------------------------------------------------

    public function getTableDetail(StockFilterRequest $request): array
    {
        $draw   = (int) $request->get('draw', 1);
        $start  = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 25);
        $search = $request->get('search')['value'] ?? '';

        $base = $this->query->baseQuery($request)
            ->selectRaw('s.source, s.pt_ph, s.pt_desc, s.cabang, s.stock, s.ptprice, s.value, s.pilar, s.exp_date, s.kelompok_ed')
            ->orderByRaw("FIELD(s.kelompok_ed, 'sudah_ed', '1_3', '4_7', '8_12', '13_17', 'gt_18')")
            ->orderByDesc('s.value');

        $total = $this->query->countGrouped(clone $base);

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('s.pt_ph', 'like', "%{$search}%")
                    ->orWhere('s.pt_desc', 'like', "%{$search}%")
                    ->orWhere('s.cabang',  'like', "%{$search}%")
                    ->orWhere('s.pilar',   'like', "%{$search}%");
            });
        }

        $filtered = $search
            ? $this->query->countGrouped(clone $base)
            : $total;

        $records = $base->offset($start)->limit($length)->get()
            ->map(function ($r) {
                $r->kelompok_label = StockQueryService::kelompokEdLabel($r->kelompok_ed);
                return $r;
            });

        return [
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $records,
        ];
    }

    // -------------------------------------------------------
    // Filter options dinamis (dropdown cabang)
    // -------------------------------------------------------

    public function getFilterOptions(): array
    {
        return Cache::remember('stock:filter_options', $this->query->cacheTtl(), function () {
            $base = DB::connection(StockQueryService::DB_DWH)->table('v_stock_ph as s');
            return [
                'cabangs' => (clone $base)->distinct()->orderBy('s.cabang')->pluck('s.cabang')->filter()->values(),
                'pilars'  => (clone $base)->distinct()->orderBy('s.pilar')->pluck('s.pilar')->filter()->values(),
            ];
        });
    }
}
