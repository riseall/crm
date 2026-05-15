<?php

namespace App\Services;

use App\Http\Requests\DashboardFilterRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class DashboardService
{
    private $sellout;

    public function __construct(SelloutQueryService $sellout)
    {
        $this->sellout = $sellout;
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
                    COUNT(DISTINCT CASE WHEN so.tgl_faktur BETWEEN ? AND ? THEN mc.cov_nik END) as total_anggota,
                    COUNT(DISTINCT CASE WHEN so.tgl_faktur BETWEEN ? AND ? THEN so.id END) as total_transaksi,
                    SUM(CASE WHEN so.tgl_faktur BETWEEN ? AND ? THEN so.netto ELSE 0 END) as prev_netto
                ", [
                    $currentStart, $currentEnd,
                    $currentStart, $currentEnd,
                    $currentStart, $currentEnd,
                    $currentStart, $currentEnd,
                    $prevStart, $prevEnd,
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
                // Gunakan baseQueryByYear yang sudah diperbaiki join-nya
                $results = $this->sellout->baseChartQueryByYear($request)
                    ->selectRaw('MONTH(so.tgl_faktur) as bulan, SUM(so.netto) as total_netto')
                    ->groupByRaw('MONTH(so.tgl_faktur)')
                    ->get()
                    ->keyBy('bulan');

                $names  = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
                $labels = [];
                $data   = [];

                for ($m = 1; $m <= 12; $m++) {
                    $labels[] = $names[$m - 1] . ' ' . $request->tahun();
                    // Round untuk menghindari desimal panjang di chart
                    $data[]   = $results->has($m) ? round((float)$results[$m]->total_netto) : 0;
                }

                return ['labels' => $labels, 'data' => $data, 'mode' => 'bulan'];
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
                    ->selectRaw('DAY(so.tgl_faktur) as hari, SUM(so.netto) as total_netto')
                    ->groupByRaw('DAY(so.tgl_faktur)')
                    ->get()
                    ->keyBy('hari');

                $totalDays = (int) date('t', strtotime($request->startDate()));
                $labels    = [];
                $data      = [];

                for ($d = 1; $d <= $totalDays; $d++) {
                    $labels[] = $d . ' ' . date('M', strtotime($request->startDate()));
                    $data[]   = $results->has($d) ? round((float)$results[$d]->total_netto) : 0;
                }

                return [
                    'labels' => $labels,
                    'data'   => $data,
                    'mode'   => 'hari',
                    'month'  => date('F Y', strtotime($request->startDate())),
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
                // Gunakan baseQuery yang sama persis dengan Card
                $results = $this->sellout->baseChartQuery($request)
                    // GUNAKAN vp.pilar (dari produk) + COALESCE agar data unmapped masuk ke "NO PILAR"
                    ->selectRaw('COALESCE(vp.pilar, "UNMAPPED") as pilar_name, SUM(so.netto) as total_netto')
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
                $results = $this->sellout->baseChartQuery($request)
                    ->selectRaw("
                    CASE 
                        WHEN mc.cov_divisi = 'Reguler' THEN 'Reguler' 
                        WHEN mc.cov_divisi IS NULL THEN 'UNMAPPED'
                        ELSE 'CMA' 
                    END as divisi_name, 
                    SUM(so.netto) as total_netto
                ")
                    ->groupBy('divisi_name')
                    ->orderBy('total_netto', 'desc')
                    ->get();

                return [
                    'labels' => $results->pluck('divisi_name')->values()->toArray(),
                    'data'   => $results->pluck('total_netto')->map(fn($v) => (float)$v)->values()->toArray(),
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
                    ->selectRaw('mc.id_coverage, mc.cov_desc, mc.cov_level, SUM(so.netto) as total_netto')
                    ->groupBy('mc.id_coverage', 'mc.cov_desc', 'mc.cov_level')
                    ->orderByDesc('total_netto')
                    ->limit(10)
                    ->get();

                return [
                    'labels' => $results->map(fn($r) => $r->cov_desc ?: $r->id_coverage)->values(),
                    'data'   => $results->pluck('total_netto')->map(fn($v) => (float)$v)->values(),
                    'levels' => $results->pluck('cov_level')->map(fn($v) => $this->sellout->numToLevel($v))->values(),
                ];
            }
        );
    }

    // -------------------------------------------------------
    // DRILL-DOWN
    // -------------------------------------------------------
    public function getDrillDown(DashboardFilterRequest $request): array
    {
        $levelName  = $request->get('level', 'GM');
        $parentId   = $request->get('coverage_id');
        $levelNum   = $this->sellout->levelToNum($levelName);

        $query = $this->sellout->baseQuery($request, true);

        // --- LOGIC FILTER HIERARKI ---
        if ($parentId && $parentId !== 'UNMAPPED') {
            // Jika sedang drill-down, ambil bawahan berdasarkan prefix ID (misal: 2.1%)
            $query->where('mc.id_coverage', 'like', $parentId . '%')
                ->where('mc.id_coverage', '!=', $parentId)
                ->where('mc.cov_level', $levelNum);
        } else {
            // Jika di level ROOT (GM), tampilkan GM DAN data yang NULL (UNMAPPED)
            $query->where(function ($q) use ($levelNum) {
                $q->where('mc.cov_level', $levelNum)
                    ->orWhereNull('mc.id_coverage');
            });
        }

        $results = $query->selectRaw("
            COALESCE(mc.id_coverage, 'UNMAPPED') as id_coverage,
            COALESCE(mc.cov_desc, 'Data Belum Termapping') as cov_desc,
            COALESCE(mc.cov_level, ?) as cov_level,
            COALESCE(mc.cov_pilar, 'N/A') as cov_pilar,
            COALESCE(mc.cov_nik, '-') as cov_nik,
            COALESCE(ma.name, 'N/A') as name,
            SUM(so.netto) as total_netto,
            COUNT(DISTINCT so.id_outlet) as total_outlet,
            COUNT(DISTINCT so.id) as total_transaksi,
            (CASE WHEN mc.id_coverage IS NULL THEN 1 ELSE 0 END) as is_unmapped
        ", [$levelNum])
            ->groupBy([
                'mc.id_coverage',
                'mc.cov_desc',
                'mc.cov_level',
                'mc.cov_pilar',
                'mc.cov_nik',
                'ma.name',
                DB::raw("(CASE WHEN mc.id_coverage IS NULL THEN 1 ELSE 0 END)")
            ])
            ->orderByDesc('total_netto')
            ->get();

        $nextLevel = $this->sellout->nextLevel($levelName);

        $results->transform(function ($row) {
            $row->cov_level_name = $this->sellout->numToLevel($row->cov_level);
            $row->is_unmapped = (int)$row->is_unmapped;
            return $row;
        });

        return [
            'level'      => $levelName,
            'level_num'  => $levelNum,
            'next_level' => $nextLevel,
            'parent_id'  => $parentId,
            'data'       => $results,
        ];
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

        $base = (clone $this->sellout->baseQuery($request))
            ->selectRaw('
                mc.cov_nik as nik,
                ma.name,
                mc.cov_level  as level,
                mc.cov_pilar  as pilar,
                mc.cov_divisi as divisi,
                mc.id_coverage,
                mc.cov_desc,
                SUM(so.netto)                as total_netto,
                COUNT(DISTINCT so.id_outlet) as total_outlet,
                COUNT(DISTINCT so.id)        as total_transaksi
            ')
            ->groupBy('mc.cov_nik', 'ma.name', 'mc.cov_level', 'mc.cov_pilar', 'mc.cov_divisi', 'mc.id_coverage', 'mc.cov_desc');

        $total = $this->sellout->countGrouped(clone $base);

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('ma.name',         'like', "%$search%")
                    ->orWhere('mc.cov_nik',    'like', "%$search%")
                    ->orWhere('mc.id_coverage', 'like', "%$search%");
            });
        }

        $filtered = $this->sellout->countGrouped(clone $base);
        $records  = $base->orderByDesc('total_netto')->offset($start)->limit($length)->get();

        // Konversi level angka → nama untuk tampilan
        $records->transform(function ($row) {
            $row->level_name = $this->sellout->numToLevel($row->level);
            return $row;
        });

        return [
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $records,
        ];
    }

    // -------------------------------------------------------
    // TABEL: History Outlet (DataTables server-side)
    // -------------------------------------------------------
    public function getTableOutlet(DashboardFilterRequest $request): array
    {
        $draw  = (int) $request->get('draw', 1);
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? '';

        $base = $this->sellout->baseQueryByYear($request)
            ->selectRaw('
      so.id_outlet,
      vcd.nama_outlet,
      mc.cov_nik,      -- Penting: Masuk Select
      ma.name,        -- Penting: Masuk Select
      mc.cov_level,
      mc.cov_pilar as pilar,
      mc.valid_from,
      mc.valid_to,
      SUM(so.netto) as total_netto,
      COUNT(DISTINCT so.id) as total_transaksi
    ')
            ->groupBy(
                'so.id_outlet',
                'vcd.nama_outlet',
                'mc.cov_nik',
                'ma.name',
                'mc.cov_level',
                'mc.cov_pilar',
                'mc.valid_from',
                'mc.valid_to'
            );

        $total = $this->sellout->countGrouped(clone $base);

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('vcd.nama_outlet', 'like', "%$search%")
                    ->orWhere('so.id_outlet', 'like', "%$search%")
                    ->orWhere('ma.name', 'like', "%$search%");
            });

            $filtered = $this->sellout->countGrouped(clone $base);
        } else {
            $filtered = $total;
        }
        $records = $base->orderByDesc('total_netto')->offset($start)->limit($length)->get();

        $records->transform(function ($row) {
            $now = now();
            // Cek status berdasarkan valid_to terhadap tgl sekarang
            $row->is_expired = false;
            if ($row->valid_to && \Carbon\Carbon::parse($row->valid_to)->lt($now)) {
                $row->is_expired = true;
            }

            $row->level_name = $this->sellout->numToLevel($row->cov_level);

            // Label jika data mapping tidak ditemukan di periode tersebut
            if (empty($row->cov_nik) || $row->cov_nik == '-') {
                $row->name = "BELUM TERCOVERAGE (Periode Ini)";
            }

            return $row;
        });

        return [
            'draw'      => $draw,
            'recordsTotal'  => $total,
            'recordsFiltered' => $filtered,
            'data'      => $records,
        ];
    }
}
