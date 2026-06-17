<?php

namespace App\Services;

use App\Http\Requests\SellingInFilterRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SellingInService
 *
 * Semua business logic dashboard Selling In ada di sini.
 * Controller hanya memanggil method ini dan return JSON.
 *
 * Pattern:
 * - Method yang di-cache: Cache::remember(key, ttl, fn() => ...)
 * - Method tidak di-cache (DataTables): langsung query ke EIS.
 * - Top PM masih memakai DB::transaction agar tmp_selling_in
 *   dan query join berikutnya memakai koneksi yang sama.
 */
class SellingInService
{
    private SellingInQueryService $query;

    public function __construct(SellingInQueryService $query)
    {
        $this->query = $query;
    }

    // -------------------------------------------------------
    // SUMMARY CARDS
    // -------------------------------------------------------

    public function getSummaryCards(SellingInFilterRequest $request): array
    {
        return Cache::remember(
            $this->query->cacheKey('summary_v2', $request),
            $this->query->cacheTtl(),
            function () use ($request) {
                $curr = $this->query->baseTransactionQuery($request)
                    ->selectRaw('
                        SUM(si.netto) as total_netto
                    ')
                    ->first();

                $prevNetto = (float) ($this->query->baseTransactionQuery(
                    $request,
                    [$request->prevStartDate(), $request->prevEndDate()]
                )->sum('si.netto') ?: 0);

                $currNetto = (float) ($curr->total_netto ?? 0);
                $endDate = Carbon::parse($request->endDate());
                $ytdStart = $endDate->copy()->startOfYear()->toDateString();
                $ytdEnd = $endDate->toDateString();
                $lastYearYtdStart = $endDate->copy()->subYear()->startOfYear()->toDateString();
                $lastYearYtdEnd = $endDate->copy()->subYear()->toDateString();

                $ytdNetto = (float) ($this->query->baseTransactionQuery(
                    $request,
                    [$ytdStart, $ytdEnd]
                )->sum('si.netto') ?: 0);

                $lastYearYtdNetto = (float) ($this->query->baseTransactionQuery(
                    $request,
                    [$lastYearYtdStart, $lastYearYtdEnd]
                )->sum('si.netto') ?: 0);

                return [
                    'total_netto' => $currNetto,
                    'prev_netto' => $prevNetto,
                    'diff_netto' => $this->calcGrowth($currNetto, $prevNetto),
                    'ytd_netto' => $ytdNetto,
                    'last_year_ytd_netto' => $lastYearYtdNetto,
                    'ytd_growth' => $this->calcGrowth($ytdNetto, $lastYearYtdNetto),
                    'ytd_period' => Carbon::parse($ytdStart)->locale('id')->translatedFormat('d M Y')
                        . ' - ' . $endDate->locale('id')->translatedFormat('d M Y'),
                    'last_year_ytd_period' => Carbon::parse($lastYearYtdStart)->locale('id')->translatedFormat('d M Y')
                        . ' - ' . Carbon::parse($lastYearYtdEnd)->locale('id')->translatedFormat('d M Y'),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // CHART: Tren harian
    // -------------------------------------------------------

    public function getChartPerHari(SellingInFilterRequest $request): array
    {
        return Cache::remember(
            $this->query->cacheKey('chart_hari', $request),
            $this->query->cacheTtl(),
            function () use ($request) {
                $results = $this->query->baseTransactionQuery($request)
                    ->selectRaw('DATE(si.tr_eff_date) as tanggal, SUM(si.netto) as total_netto')
                    ->groupByRaw('DATE(si.tr_eff_date)')
                    ->orderByRaw('DATE(si.tr_eff_date)')
                    ->get()
                    ->keyBy('tanggal');

                $labels    = [];
                $data      = [];

                foreach (CarbonPeriod::create($request->startDate(), $request->endDate()) as $date) {
                    $key = $date->toDateString();
                    $labels[] = $date->locale('id')->translatedFormat('d M');
                    $data[] = round((float) ($results[$key]->total_netto ?? 0));
                }

                return [
                    'labels' => $labels,
                    'data' => $data,
                    'period' => $this->periodLabel($request),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // CHART: YTD per Bulan
    // -------------------------------------------------------

    public function getChartPerBulan(SellingInFilterRequest $request): array
    {
        return Cache::remember(
            $this->query->cacheKey('chart_bulan', $request),
            $this->query->cacheTtl(),
            function () use ($request) {
                $results = $this->query->baseTransactionQuery($request)
                    ->selectRaw("DATE_FORMAT(si.tr_eff_date, '%Y-%m') as periode, SUM(si.netto) as total_netto")
                    ->groupByRaw("DATE_FORMAT(si.tr_eff_date, '%Y-%m')")
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
                    $data[] = round((float) ($results[$key]->total_netto ?? 0));
                }

                return [
                    'labels' => $labels,
                    'data' => $data,
                    'period' => $this->periodLabel($request),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // CHART: Distribusi per Pilar
    // -------------------------------------------------------

    public function getChartPerPilar(SellingInFilterRequest $request): array
    {
        return Cache::remember(
            $this->query->cacheKey('chart_pilar', $request),
            $this->query->cacheTtl(),
            function () use ($request) {
                $results = $this->query->baseTransactionQuery($request)
                    ->selectRaw($this->query->promoPilarSql('si.tr_promo') . " as pilar, SUM(si.netto) as total_netto")
                    ->whereIn('si.tr_promo', [
                        101, 102, 103, 104, 105,
                        206, 207, 208, 209,
                        311, 312, 313, 314, 315, 316, 317, 318, 319,
                        417, 418, 419, 420,
                        700,
                    ])
                    ->groupByRaw($this->query->promoPilarSql('si.tr_promo'))
                    ->orderByDesc('total_netto')
                    ->get();

                $total = $results->sum('total_netto') ?: 1;

                return [
                    'labels' => $results->pluck('pilar'),
                    'data'   => $results->pluck('total_netto')->map(fn($v) => round((float) $v)),
                    'pct'    => $results->pluck('total_netto')->map(fn($v) => round(($v / $total) * 100, 1)),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // TOP PM
    // -------------------------------------------------------

    public function getTopPm(SellingInFilterRequest $request, int $limit = 10): array
    {
        return Cache::remember(
            $this->query->cacheKey('top_pm', $request, (string) $limit),
            $this->query->cacheTtl(),
            function () use ($request, $limit) {
                return DB::transaction(function () use ($request, $limit) {
                    $results = $this->query->baseQuery($request)
                        ->join(DB::raw($this->query->productCoverageTable('mp')), 'si.tr_part', '=', 'mp.pt_ph')
                        ->join(DB::raw($this->query->dwhTable('crm_mst_mappings') . ' as mc'), function ($join) {
                            $join->on('mp.id_coverage', '=', DB::raw('mc.id_coverage COLLATE utf8mb4_0900_ai_ci'))
                                ->whereNull('mc.deleted_at')
                                ->where('mc.is_active', true);
                        })
                        ->join(DB::raw($this->query->authTable('users') . ' as u'), DB::raw('mc.cov_nik COLLATE utf8mb4_0900_ai_ci'), '=', 'u.nik')
                        ->selectRaw('
                            mc.cov_nik as nik,
                            u.name,
                            COALESCE(
                                NULLIF(MAX(TRIM(mc.cov_pilar COLLATE utf8mb4_0900_ai_ci)), \'\'),
                                NULLIF(MAX(' . $this->query->promoPilarSql('si.tr_promo') . '), \'\'),
                                \'EXPORT\'
                            ) as pilar,
                            SUM(si.netto) as total_netto,
                            COUNT(DISTINCT si.tr_part) as total_sku
                        ')
                        ->groupBy('mc.cov_nik', 'u.name')
                        ->orderByDesc('total_netto')
                        ->limit($limit)
                        ->get();

                    $max = $results->max('total_netto') ?: 1;

                    return $results->map(fn($r) => [
                        'nik'        => $r->nik,
                        'name'       => $r->name,
                        'pilar'      => $r->pilar,
                        'total_netto' => round((float) $r->total_netto),
                        'total_sku'  => (int) $r->total_sku,
                        'pct_of_max' => round(($r->total_netto / $max) * 100, 1),
                    ])->values()->toArray();
                });
            }
        );
    }

    // -------------------------------------------------------
    // TOP PRODUK
    // -------------------------------------------------------

    public function getTopProduk(SellingInFilterRequest $request, int $limit = 10): array
    {
        return Cache::remember(
            $this->query->cacheKey('top_produk', $request, (string) $limit),
            $this->query->cacheTtl(),
            function () use ($request, $limit) {
                $results = $this->query->baseTransactionQuery($request)
                    ->selectRaw("
                        si.tr_part as kode,
                        COALESCE(
                            NULLIF(MAX(TRIM(CONCAT_WS(' ', si.tr_desc1, si.tr_desc2))), ''),
                            si.tr_part
                        ) as nama,
                        " . $this->query->promoPilarSql('si.tr_promo') . " as pilar,
                        SUM(si.netto)                    as total_netto,
                        SUM(si.tr_qty)                   as total_qty
                    ")
                    ->groupBy('si.tr_part', 'si.tr_promo')
                    ->orderByDesc('total_netto')
                    ->limit($limit)
                    ->get();

                $totalAll = $this->query->baseTransactionQuery($request)->sum('si.netto') ?: 1;

                return $results->map(fn($r) => [
                    'kode'       => $r->kode,
                    'nama'       => $r->nama,
                    'pilar'      => $r->pilar,
                    'total_netto' => round((float) $r->total_netto),
                    'total_qty'  => round((float) $r->total_qty),
                    'pct'        => round(($r->total_netto / $totalAll) * 100, 1),
                ])->values()->toArray();
            }
        );
    }

    // -------------------------------------------------------
    // TABEL DETAIL — DataTables server-side
    // -------------------------------------------------------

    public function getTableDetail(SellingInFilterRequest $request): array
    {
        $draw   = (int) $request->get('draw', 1);
        $start  = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 25);
        $search = $request->get('search')['value'] ?? '';

        $base = $this->query->baseQuery($request)
            ->leftJoin(DB::raw($this->query->productCoverageTable('mp')), function ($join) {
                $join->on('si.tr_part', '=', DB::raw('mp.pt_ph COLLATE utf8mb4_0900_ai_ci'));
            })
            ->leftJoin(DB::raw($this->query->dwhTable('crm_mst_mappings') . ' as mc'), function ($join) {
                $join->on('mp.id_coverage', '=', DB::raw('mc.id_coverage COLLATE utf8mb4_0900_ai_ci'))
                    ->whereNull('mc.deleted_at')
                    ->where('mc.is_active', true);
            })
            ->leftJoin(DB::raw($this->query->authTable('users') . ' as u'), function ($join) {
                $join->on(
                    DB::raw('mc.cov_nik COLLATE utf8mb4_0900_ai_ci'),
                    '=',
                    DB::raw('u.nik COLLATE utf8mb4_0900_ai_ci')
                );
            })
            ->selectRaw("
                si.tr_eff_date as tanggal,
                si.tr_part     as kode_produk,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', si.tr_desc1, si.tr_desc2)), ''),
                    si.tr_part
                ) as nama_produk,
                " . $this->query->promoPilarSql('si.tr_promo') . " as pilar,
                COALESCE(u.name, '-') as pm_name,
                COALESCE(mc.cov_nik, '-') as pm_nik,
                '-' as promotion_group,
                si.tr_price    as harga,
                si.tr_qty      as qty,
                si.netto       as netto
            ");

        $total = $this->query->countGrouped(clone $base);

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('si.tr_part',  'like', "%{$search}%")
                    ->orWhereRaw("TRIM(CONCAT_WS(' ', si.tr_desc1, si.tr_desc2)) like ?", ["%{$search}%"])
                    ->orWhere('u.name', 'like', "%{$search}%")
                    ->orWhere('mc.cov_nik', 'like', "%{$search}%")
                    ->orWhereRaw($this->query->promoPilarSql('si.tr_promo') . ' like ?', ["%{$search}%"]);
            });
        }

        $filtered = $search
            ? $this->query->countGrouped(clone $base)
            : $total;

        $records = $base
            ->orderByDesc('si.netto')
            ->offset($start)
            ->limit($length)
            ->get();

        return [
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $records,
        ];
    }

    // -------------------------------------------------------
    // HELPER PRIVATE
    // -------------------------------------------------------

    private function calcGrowth(float $curr, float $prev): float
    {
        if ($prev > 0) {
            return round((($curr - $prev) / $prev) * 100, 2);
        }

        return $curr > 0 ? 100.0 : 0.0;
    }

    private function periodLabel(SellingInFilterRequest $request): string
    {
        $start = Carbon::parse($request->startDate())->locale('id')->translatedFormat('d M Y');
        $end = Carbon::parse($request->endDate())->locale('id')->translatedFormat('d M Y');

        return $start . ' - ' . $end;
    }
}
