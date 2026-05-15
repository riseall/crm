<?php

namespace App\Services;

use App\Http\Requests\DashboardFilterRequest;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SelloutQueryService
{
    private const CACHE_TTL = 300;

    public const LEVEL_MAP = [
        'GM'  => '1',
        'RM'  => '2',
        'SAM' => '3',
        'AM'  => '4',
        'MR'  => '5',
    ];

    /** Urutan hierarki (nama) */
    public const LEVEL_ORDER = ['GM', 'RM', 'SAM', 'AM', 'MR'];

    /**
     * Flag agar tmp_sellout hanya dibuat sekali per instance (= per HTTP request).
     * Pastikan service ini di-bind sebagai ->bind() bukan ->singleton() di AppServiceProvider.
     */
    private bool $tempTableCreated = false;
    private ?string $currentRange = null;

    // -------------------------------------------------------
    // TEMP TABLE — materialize union 3 distributor sekali saja
    // -------------------------------------------------------
    private function ensureSelloutTempTable(array $dateRange): void
    {
        [$start, $end] = $this->normalizeDateRange($dateRange);
        $rangeKey = $start . '_' . $end;

        if ($this->tempTableCreated && $this->currentRange === $rangeKey) {
            return;
        }

        $this->currentRange = $rangeKey;
        DB::statement('DROP TEMPORARY TABLE IF EXISTS tmp_sellout');

        DB::statement("
            CREATE TEMPORARY TABLE tmp_sellout (
                id           BIGINT,
                tgl_faktur   DATETIME,
                no_faktur    VARCHAR(100) COLLATE utf8mb4_unicode_ci,
                id_outlet    VARCHAR(50)  COLLATE utf8mb4_unicode_ci,
                id_cbg       VARCHAR(50)  COLLATE utf8mb4_unicode_ci,
                code_product VARCHAR(50)  COLLATE utf8mb4_unicode_ci,
                netto        DECIMAL(20,4),
                dist         VARCHAR(10)  COLLATE utf8mb4_unicode_ci,
                KEY idx_outlet  (id_outlet),
                KEY idx_product (code_product),
                KEY idx_cbg     (id_cbg),
                KEY idx_dist    (dist),
                KEY idx_tgl     (tgl_faktur)
            ) ENGINE=InnoDB
        ");

        // KFTD
        DB::statement("
            INSERT INTO tmp_sellout
            SELECT
                id_selling_out,
                posting_date,
                CONVERT(billing_no    USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                CONVERT(ship_to_party USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                CONVERT(sales_office  USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                CONVERT(material      USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                ((total_penjualan + total_discount) - (dis_zd02_amount + dis_zd04_amount)),
                'KFTD' COLLATE utf8mb4_unicode_ci
            FROM selling_out_kftd
            WHERE principle = '7000000344'
              AND posting_date BETWEEN ? AND ?
        ", [$start, $end]);

        // AAM
        DB::statement("
            INSERT INTO tmp_sellout
            SELECT
                id,
                posting_date,
                CONVERT(billing_no    USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                CONVERT(ship_to_party USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                CONVERT(sales_office  USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                CONVERT(material      USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                nilai_netto,
                'AAM' COLLATE utf8mb4_unicode_ci
            FROM selling_out_aam
            WHERE posting_date BETWEEN ? AND ?
        ", [$start, $end]);

        // METD
        DB::statement("
            INSERT INTO tmp_sellout
            SELECT
                id,
                tgl_faktur,
                CONVERT(no_faktur     USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                CONVERT(id_outlet     USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                CONVERT(kode_cbg_ph   USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                CONVERT(kode_brg_metd USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                netto,
                'METD' COLLATE utf8mb4_unicode_ci
            FROM selling_out_metd
            WHERE tgl_faktur BETWEEN ? AND ?
        ", [$start, $end]);

        $this->tempTableCreated = true;
    }

    // -------------------------------------------------------
    // STANDARD JOINS (tidak berubah, tetap pakai alias 'so')
    // -------------------------------------------------------
    private function applyStandardJoins($query)
    {
        return $query
            ->leftJoin('v_pt_mstr_dist as vp', function ($join) {
                $join->on('so.code_product', '=', 'vp.pt_dist')
                    ->on('so.dist', '=', 'vp.dist');
            })
            ->leftJoin('mst_cust_dist as vcd', function ($join) {
                $join->on('so.id_outlet', '=', 'vcd.id_outlet')
                    ->on('so.id_cbg', '=', 'vcd.id_cbg_dist');
            })
            ->leftJoin('mst_outlets as mo', function ($join) {
                $join->on('so.id_outlet', '=', 'mo.id_outlet')
                    ->on('so.id_cbg',    '=', 'mo.id_cbg')
                    ->on('vp.pilar',     '=', 'mo.pilar');
            });
    }

    /**
     * Join minimal untuk chart.
     * Tidak ikut join users / mst_cust_dist karena chart hanya butuh agregasi.
     */
    private function applyChartJoins($query)
    {
        return $query
            ->leftJoin('v_pt_mstr_dist as vp', function ($join) {
                $join->on('so.code_product', '=', 'vp.pt_dist')
                    ->on('so.dist', '=', 'vp.dist');
            })
            ->leftJoin('mst_outlets as mo', function ($join) {
                $join->on('so.id_outlet', '=', 'mo.id_outlet')
                    ->on('so.id_cbg', '=', 'mo.id_cbg')
                    ->on('vp.pilar', '=', 'mo.pilar');
            });
    }

    private function normalizeDateRange(array $dateRange): array
    {
        return [
            Carbon::parse($dateRange[0])->startOfDay()->toDateTimeString(),
            Carbon::parse($dateRange[1])->endOfDay()->toDateTimeString(),
        ];
    }

    private function applySourceDateRange(Builder $query, string $column, ?array $dateRange): Builder
    {
        if (!$dateRange) {
            return $query;
        }

        [$start, $end] = $this->normalizeDateRange($dateRange);

        return $query->whereBetween($column, [$start, $end]);
    }

    public function selloutUnion(?array $dateRange = null): Builder
    {
        if ($dateRange) {
            $this->ensureSelloutTempTable($dateRange);
            return DB::table('tmp_sellout');
        }

        // Fallback: union biasa tanpa filter tanggal
        $col = "CONVERT(%s USING utf8mb4) COLLATE utf8mb4_unicode_ci";

        $kftd = DB::table('selling_out_kftd')
            ->select([
                'id_selling_out as id',
                'posting_date as tgl_faktur',
                DB::raw(sprintf("$col as no_faktur",    'billing_no')),
                DB::raw(sprintf("$col as id_outlet",    'ship_to_party')),
                DB::raw(sprintf("$col as id_cbg",       'sales_office')),
                DB::raw(sprintf("$col as code_product", 'material')),
                DB::raw("((total_penjualan + total_discount) - (dis_zd02_amount + dis_zd04_amount)) AS netto"),
                DB::raw("'KFTD' COLLATE utf8mb4_unicode_ci as dist"),
            ])
            ->whereRaw("principle = '7000000344'");

        $aam = DB::table('selling_out_aam')
            ->select([
                'id',
                'posting_date as tgl_faktur',
                DB::raw(sprintf("$col as no_faktur",    'billing_no')),
                DB::raw(sprintf("$col as id_outlet",    'ship_to_party')),
                DB::raw(sprintf("$col as id_cbg",       'sales_office')),
                DB::raw(sprintf("$col as code_product", 'material')),
                'nilai_netto as netto',
                DB::raw("'AAM' COLLATE utf8mb4_unicode_ci as dist"),
            ]);

        $metd = DB::table('selling_out_metd')
            ->select([
                'id',
                'tgl_faktur',
                DB::raw(sprintf("$col as no_faktur",    'no_faktur')),
                DB::raw(sprintf("$col as id_outlet",    'id_outlet')),
                DB::raw(sprintf("$col as id_cbg",       'kode_cbg_ph')),
                DB::raw(sprintf("$col as code_product", 'kode_brg_metd')),
                'netto',
                DB::raw("'METD' COLLATE utf8mb4_unicode_ci as dist"),
            ]);

        return $kftd->unionAll($aam)->unionAll($metd);
    }

    // -------------------------------------------------------
    // BASE QUERY
    // -------------------------------------------------------
    public function baseQuery(
        DashboardFilterRequest $request,
        bool $isHierarchical = false,
        ?array $dateRange = null
    ): Builder {
        $range = $dateRange ?? [$request->startDate(), $request->endDate()];

        // Pastikan temp table sudah ada sebelum query dijalankan
        $this->ensureSelloutTempTable($range);

        // FROM tmp_sellout langsung (bukan subquery union lagi)
        $query = DB::table('tmp_sellout as so');
        $query = $this->applyStandardJoins($query);

        // 1. Join ke Mapping dengan filter tanggal di ON clause
        if ($isHierarchical) {
            $targetLevelNum = $this->levelToNum($request->get('level', 'GM'));
            $query->leftJoin('mst_mappings as mc', function ($join) use ($targetLevelNum) {
                $join->on('mo.id_coverage', 'like', DB::raw("CONCAT(mc.id_coverage, '%')"))
                    ->where('mc.cov_level', $targetLevelNum)
                    ->whereRaw('so.tgl_faktur >= mc.valid_from')
                    ->where(function ($q) {
                        $q->whereNull('mc.valid_to')
                            ->orWhereRaw('so.tgl_faktur <= mc.valid_to');
                    });
            });
        } else {
            $query->leftJoin('mst_mappings as mc', function ($join) {
                $join->on('mo.id_coverage', '=', 'mc.id_coverage')
                    ->whereRaw('so.tgl_faktur >= mc.valid_from')
                    ->where(function ($q) {
                        $q->whereNull('mc.valid_to')
                            ->orWhereRaw('so.tgl_faktur <= mc.valid_to');
                    });
            });
        }

        // 2. Join User
        $query->leftJoin('users as ma', 'mc.cov_nik', '=', 'ma.nik');

        // 3. Filter request
        if (!$isHierarchical && $request->filled('level')) {
            $query->where('mc.cov_level', $this->levelToNum($request->level));
        }

        if ($request->filled('pilar')) {
            $query->where('vp.pilar', $request->pilar);
        }

        if ($request->filled('distributor')) {
            $query->where('so.dist', $request->distributor);
        }

        return $query;
    }

    // -------------------------------------------------------
    // BASE QUERY BY YEAR — chart trend 12 bulan
    // -------------------------------------------------------
    public function baseQueryByYear(DashboardFilterRequest $request): Builder
    {
        $year = (int) $request->tahun();
        $yearRange = [
            Carbon::create($year, 1, 1, 0, 0, 0)->toDateString(),
            Carbon::create($year, 12, 31, 23, 59, 59)->toDateString(),
        ];

        return $this->baseQuery($request, false, $yearRange);
    }

    // -------------------------------------------------------
    // LIGHTWEIGHT BASE QUERY UNTUK CHART
    // -------------------------------------------------------
    public function baseChartQuery(
        DashboardFilterRequest $request,
        ?array $dateRange = null
    ): Builder {
        $range = $dateRange ?? [$request->startDate(), $request->endDate()];

        $this->ensureSelloutTempTable($range);

        $query = DB::table('tmp_sellout as so');
        $query = $this->applyChartJoins($query);

        $query->leftJoin('mst_mappings as mc', function ($join) {
            $join->on('mo.id_coverage', '=', 'mc.id_coverage')
                ->whereRaw('so.tgl_faktur >= mc.valid_from')
                ->where(function ($q) {
                    $q->whereNull('mc.valid_to')
                        ->orWhereRaw('so.tgl_faktur <= mc.valid_to');
                });
        });

        if ($request->filled('level')) {
            $query->where('mc.cov_level', $this->levelToNum($request->level));
        }

        if ($request->filled('pilar')) {
            $query->where('vp.pilar', $request->pilar);
        }

        if ($request->filled('distributor')) {
            $query->where('so.dist', $request->distributor);
        }

        return $query;
    }

    public function baseChartQueryByYear(DashboardFilterRequest $request): Builder
    {
        $year = (int) $request->tahun();
        $yearRange = [
            Carbon::create($year, 1, 1, 0, 0, 0)->toDateString(),
            Carbon::create($year, 12, 31, 23, 59, 59)->toDateString(),
        ];

        return $this->baseChartQuery($request, $yearRange);
    }

    // -------------------------------------------------------
    // OUTLET HISTORY QUERY
    // -------------------------------------------------------
    public function outletHistoryQuery(DashboardFilterRequest $request)
    {
        $this->ensureSelloutTempTable([$request->startDate(), $request->endDate()]);

        return DB::table('tmp_sellout as so')
            // Gunakan LEFT JOIN ke master outlet agar yang belum termapping tidak hilang
            ->leftJoin('mst_cust_dist as vcd', function ($join) {
                $join->on('so.id_outlet', '=', 'vcd.id_outlet')
                    ->on('so.id_cbg', '=', 'vcd.id_cbg_dist');
            })
            ->leftJoin('v_pt_mstr_dist as vp', function ($join) {
                $join->on('so.code_product', '=', 'vp.pt_dist')
                    ->on('so.dist', '=', 'vp.dist');
            })
            ->leftJoin('mst_outlets as mo', 'so.id_outlet', '=', 'mo.id_outlet')
            ->leftJoin('mst_mappings as mc', 'mo.id_coverage', '=', 'mc.id_coverage')
            ->leftJoin('users as ma', 'mc.cov_nik', '=', 'ma.nik')
            ->when($request->get('pilar'), fn($q, $p) => $q->where('vp.pilar', $p))
            ->when($request->get('coverage_id'), fn($q, $c) => $q->where('mo.id_coverage', $c))
            ->when($request->get('level'), fn($q, $l) => $q->where('mc.cov_level', $this->levelToNum($l)))
            ->select([
                'so.dist',
                'so.id_outlet',
                'vcd.nama_outlet',
                'mo.id_coverage',
                'mc.cov_desc',
                'mc.cov_nik',
                'ma.name as user_name',
                'mc.cov_level',
                'vp.pilar',
                DB::raw("CONCAT(MONTHNAME(so.tgl_faktur), ' ', YEAR(so.tgl_faktur)) as period"),
                DB::raw('COUNT(so.id) as total_transaksi'),
                DB::raw('SUM(so.netto) as total_netto'),
                'mc.valid_from',
                'mc.valid_to'
            ])
            ->groupBy(
                'so.dist',
                'so.id_outlet',
                'vcd.nama_outlet',
                'mo.id_coverage',
                'mc.cov_desc',
                'mc.cov_nik',
                'user_name',
                'mc.cov_level',
                'vp.pilar',
                'period',
                'mc.valid_from',
                'mc.valid_to'
            );
    }

    // -------------------------------------------------------
    // Count helper untuk DataTables grouped result
    // -------------------------------------------------------
    public function countGrouped(Builder $query): int
    {
        return DB::query()
            ->fromSub($query, 'agg')
            ->count();
    }

    // -------------------------------------------------------
    // Cache key generator
    // -------------------------------------------------------
    public function cacheKey(string $prefix, DashboardFilterRequest $request, ?string $extra = null): string
    {
        $params = [
            $request->bulan(),
            $request->tahun(),
            $request->get('level', ''),
            $request->get('pilar', ''),
            $request->get('distributor', ''),
            $request->get('coverage_id', ''),
            $extra ?? '',
        ];

        return "dashboard:{$prefix}:" . md5(implode('|', $params));
    }

    public function cacheTtl(): int
    {
        return self::CACHE_TTL;
    }

    /** 'GM' → '1', 'RM' → '2', dst */
    public function levelToNum(string $level): string
    {
        return self::LEVEL_MAP[$level] ?? $level;
    }

    /** '1' → 'GM', '2' → 'RM', null → 'Unmapped' */
    public function numToLevel(?string $num): string
    {
        if (is_null($num)) {
            return 'Unmapped';
        }

        return array_flip(self::LEVEL_MAP)[$num] ?? 'Unknown';
    }

    /** Level berikutnya dalam hierarki. 'RM' → 'SAM', 'MR' → null */
    public function nextLevel(string $level): ?string
    {
        $idx = array_search($level, self::LEVEL_ORDER);
        return self::LEVEL_ORDER[$idx + 1] ?? null;
    }
}
