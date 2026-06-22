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
                        COUNT(DISTINCT s.pt_part) as total_sku
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
                    ->selectRaw('s.kelompok_ed, SUM(s.value) as total_value, COUNT(DISTINCT s.pt_part) as total_sku')
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
    // CHART: Aging per source (stacked bar)
    // -------------------------------------------------------

    public function getChartAgingPerSource(StockFilterRequest $request): array
    {
        return Cache::remember(
            $this->query->cacheKey('chart_aging_source', $request),
            $this->query->cacheTtl(),
            function () use ($request) {
                $results  = $this->query->baseQuery($request)
                    ->selectRaw('s.source, s.kelompok_ed, SUM(s.value) as total_value')
                    ->groupBy('s.source', 's.kelompok_ed')
                    ->get();

                $sources  = $results->pluck('source')->unique()->sort()->values();
                $order    = StockQueryService::kelompokEdOrder();
                $datasets = [];

                foreach ($order as $key) {
                    $data = $sources->map(function ($src) use ($results, $key) {
                        $row = $results->first(fn($r) => $r->source === $src && $r->kelompok_ed === $key);
                        return round((float) ($row->total_value ?? 0));
                    })->values()->toArray();

                    // Skip dataset yang semua nilainya 0
                    if (array_sum($data) === 0) continue;

                    $datasets[] = [
                        'label' => StockQueryService::kelompokEdLabel($key),
                        'key'   => $key,
                        'data'  => $data,
                    ];
                }

                return [
                    'labels'   => $sources->values()->toArray(),
                    'datasets' => $datasets,
                ];
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
                    ->selectRaw('s.source, SUM(s.value) as total_value, SUM(s.stock) as total_stock, COUNT(DISTINCT s.pt_part) as total_sku')
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
    // Produk prioritas tindakan (sudah_ed, 1_3, 4_7)
    // -------------------------------------------------------

    public function getProdukPrioritas(StockFilterRequest $request, int $limit = 10): array
    {
        return Cache::remember(
            $this->query->cacheKey('produk_prioritas', $request, (string) $limit),
            $this->query->cacheTtl(),
            function () use ($request, $limit) {
                return $this->query->baseQuery($request)
                    ->whereIn('s.kelompok_ed', ['sudah_ed', '1_3', '4_7'])
                    ->selectRaw('
                        s.pt_part,
                        s.pt_desc,
                        s.pilar,
                        s.source,
                        s.cabang,
                        s.kelompok_ed,
                        MIN(s.exp_date)  as earliest_exp,
                        SUM(s.stock)     as total_stock,
                        SUM(s.value)     as total_value
                    ')
                    ->groupBy('s.pt_part', 's.pt_desc', 's.pilar', 's.source', 's.cabang', 's.kelompok_ed')
                    ->orderByRaw("FIELD(s.kelompok_ed, 'sudah_ed', '1_3', '4_7')")
                    ->orderByDesc('total_value')
                    ->limit($limit)
                    ->get()
                    ->map(fn($r) => [
                        'pt_part'        => $r->pt_part,
                        'pt_desc'        => $r->pt_desc,
                        'pilar'          => $r->pilar,
                        'source'         => $r->source,
                        'cabang'         => $r->cabang,
                        'kelompok_ed'    => $r->kelompok_ed,
                        'kelompok_label' => StockQueryService::kelompokEdLabel($r->kelompok_ed),
                        'earliest_exp'   => $r->earliest_exp,
                        'total_stock'    => round((float) $r->total_stock, 2),
                        'total_value'    => round((float) $r->total_value),
                    ])
                    ->values()->toArray();
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
            ->selectRaw('s.source, s.pt_part, s.pt_desc, s.cabang, s.stock, s.ptprice, s.value, s.pilar, s.exp_date, s.kelompok_ed')
            ->orderByRaw("FIELD(s.kelompok_ed, 'sudah_ed', '1_3', '4_7', '8_12', '13_17', 'gt_18')")
            ->orderByDesc('s.value');

        $total = $this->query->countGrouped(clone $base);

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('s.pt_part', 'like', "%{$search}%")
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
