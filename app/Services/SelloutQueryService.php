<?php

namespace App\Services;

use App\Http\Requests\DashboardFilterRequest;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SelloutQueryService
{
    private const CONN_DWH = 'db_dwh';
    private const CONN_AUTH = 'mysql';
    private const CACHE_TTL = 300;
    private const CACHE_VERSION = 'v18';
    private const SUMMARY_TABLE = 'crm_sellout_summary';

    private function dwhDatabaseName(): string
    {
        return DB::connection(self::CONN_DWH)->getDatabaseName();
    }

    private function authDatabaseName(): string
    {
        return DB::connection(self::CONN_AUTH)->getDatabaseName();
    }

    private CoverageAccessService $coverageAccess;

    public const LEVEL_MAP = [
        'GM'  => '1',
        'RM'  => '2',
        'SAM' => '3',
        'AM'  => '4',
        'MR'  => '5',
    ];

    public const LEVEL_ORDER = ['GM', 'RM', 'SAM', 'AM', 'MR'];

    public function __construct(CoverageAccessService $coverageAccess)
    {
        $this->coverageAccess = $coverageAccess;
    }

    private function dwhTable(string $table): string
    {
        return $this->dwhDatabaseName() . '.' . $table;
    }

    private function authTable(string $table): string
    {
        return $this->authDatabaseName() . '.' . $table;
    }

    private function pilarMatchSql(string $productPilarCol, string $mappingPilarCol): string
    {
        return "({$productPilarCol} = {$mappingPilarCol} OR ({$mappingPilarCol} = 'ETHICAL' AND {$productPilarCol} IN ('ETHICOM', 'ETHIPROM')))";
    }

    private function normalizePilarValue(?string $pilar): ?string
    {
        if ($pilar === null || $pilar === '') {
            return null;
        }

        return strtoupper(trim($pilar));
    }

    // -------------------------------------------------------
    // STANDARD JOINS - Direct join dari summary table via id_konversi
    // -------------------------------------------------------
    private function applyStandardJoins($query)
    {
        return $query
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_cust') . ' as master'), 'so.id_konversi', '=', 'master.id_konversi')
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_cust_cov') . ' as mo'), function ($join) {
                $join->on('so.id_konversi', '=', 'mo.id_konversi')
                     ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mo.pilar'));
            });
    }

    /**
     * Filter minimal untuk chart - menggunakan whereExists untuk mencegah duplikasi baris outlet multi-coverage
     */
    private function applyChartJoins($query)
    {
        return $query->whereExists(function ($exists) {
            $exists->select(DB::raw(1))
                ->from(DB::raw($this->dwhTable('crm_mst_cust_cov') . ' as mo'))
                ->whereColumn('mo.id_konversi', 'so.id_konversi')
                ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mo.pilar'));

            $this->coverageAccess->applyScope($exists, 'mo.id_coverage');
        });
    }

    private function normalizeDateRange(array $dateRange): array
    {
        return [
            Carbon::parse($dateRange[0])->startOfDay()->toDateTimeString(),
            Carbon::parse($dateRange[1])->endOfDay()->toDateTimeString(),
        ];
    }

    private function mappingJoinCallback(): \Closure
    {
        return function ($join) {
            $join->on('mo.id_coverage', '=', 'mc.id_coverage')
                ->whereNull('mc.deleted_at')
                ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mc.cov_pilar'));

            $join->where(function ($q) {
                $q->whereNull('mc.valid_from')
                    ->orWhereRaw('so.tgl_faktur >= mc.valid_from');
            })
            ->where(function ($q) {
                $q->whereNull('mc.valid_to')
                    ->orWhereRaw('so.tgl_faktur <= mc.valid_to');
            });
        };
    }

    private function applyMappingValidity($query, string $alias): void
    {
        $query->where(function ($q) use ($alias) {
            $q->whereNull("{$alias}.valid_from")
                ->orWhereRaw("so.tgl_faktur >= {$alias}.valid_from");
        })
            ->where(function ($q) use ($alias) {
                $q->whereNull("{$alias}.valid_to")
                    ->orWhereRaw("so.tgl_faktur <= {$alias}.valid_to");
            });
    }

    private function outletHistoryDateRange(DashboardFilterRequest $request): array
    {
        $months = $request->bulanList();
        $year = (int) $request->tahun();

        if (count($months) > 1) {
            return [
                Carbon::create($year, min($months), 1)->startOfMonth()->toDateTimeString(),
                Carbon::create($year, max($months), 1)->endOfMonth()->toDateTimeString(),
            ];
        }

        return $this->normalizeDateRange([$request->startDate(), $request->endDate()]);
    }

    private function enrichedSummaryQuery(
        DashboardFilterRequest $request,
        array $dateRange,
        bool $includeUser = true,
        bool $includeOutletName = true,
        bool $forChart = false,
        ?array $months = null,
        ?string $coverageId = null
    ): Builder {
        [$start, $end] = $this->normalizeDateRange($dateRange);

        $query = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so');

        $user = auth()->user();
        $needsOutletFilter = ($user && !$this->coverageAccess->isGlobalAccessUser($user))
            || $request->filled('level')
            || $request->filled('coverage_nik')
            || ($coverageId !== null && trim($coverageId) !== '');

        if ($forChart) {
            if ($needsOutletFilter) {
                $query = $this->applyChartJoins($query);
            }
        } else {
            $query = $this->applyStandardJoins($query);
        }

        // ponytail: skip mc join for chart queries when no mapping filters (level/nik/coverage) are set to avoid 1-to-many row duplication and memory overhead
        $needsMappingJoin = !$forChart
            || $request->filled('level')
            || $request->filled('coverage_nik')
            || ($coverageId !== null && trim($coverageId) !== '');

        if ($needsMappingJoin) {
            $query->leftJoin(
                DB::raw($this->dwhTable('crm_mst_mappings') . ' as mc'),
                $this->mappingJoinCallback()
            );
        }

        if ($includeUser) {
            $query->leftJoin(
                DB::raw($this->authTable('users') . ' as ma'),
                'mc.cov_nik',
                '=',
                'ma.nik'
            );
        }

        $query->whereBetween('so.tgl_faktur', [$start, $end]);

        if (!empty($months)) {
            $year = (int) Carbon::parse($start)->year;

            $query->where(function ($q) use ($months, $year) {
                foreach ($months as $month) {
                    $q->orWhereBetween('so.tgl_faktur', [
                        Carbon::create($year, (int) $month, 1)->startOfMonth()->toDateTimeString(),
                        Carbon::create($year, (int) $month, 1)->endOfMonth()->toDateTimeString(),
                    ]);
                }
            });
        }

        if ($request->filled('coverage_nik')) {
            $query->where('mc.cov_nik', $request->get('coverage_nik'));
        } elseif ($coverageId !== null && trim($coverageId) !== '') {
            $query->where('mc.id_coverage', $coverageId);
        }

        if ($request->filled('outlet_id')) {
            $query->where('so.id_outlet', $request->get('outlet_id'));
        }

        if (!$forChart) {
            $query = $this->coverageAccess->applyScope($query, 'mo.id_coverage');
        }

        if ($request->filled('level')) {
            $query->where('mc.cov_level', $this->levelToNum($request->get('level')));
        }

        $this->applyGlobalFilters($query, $request, 'so.pilar_sub', 'so.dist', 'so.code_prod_ph');

        $selects = [
            'so.id',
            'so.source_table',
            'so.source_id',
            'so.tgl_faktur',
            'so.dist',
            'so.id_outlet',
            'so.id_konversi',
            'so.id_cbg',
            'so.code_product',
            'so.segment_mi',
            'so.netto',
            'so.pilar_sub as product_pilar',
            $needsMappingJoin ? 'mc.id_coverage as coverage_id' : DB::raw('NULL as coverage_id'),
            $needsMappingJoin ? 'mc.cov_desc as coverage_desc' : DB::raw('NULL as coverage_desc'),
            $needsMappingJoin ? 'mc.cov_nik as coverage_nik' : DB::raw('NULL as coverage_nik'),
            $needsMappingJoin ? 'mc.cov_level as coverage_level' : DB::raw('NULL as coverage_level'),
            $needsMappingJoin ? 'mc.cov_pilar as coverage_pilar' : DB::raw('NULL as coverage_pilar'),
            $needsMappingJoin ? 'mc.cov_divisi as coverage_divisi' : DB::raw('NULL as coverage_divisi'),
            $needsMappingJoin ? 'mc.valid_from as coverage_valid_from' : DB::raw('NULL as coverage_valid_from'),
            $needsMappingJoin ? 'mc.valid_to as coverage_valid_to' : DB::raw('NULL as coverage_valid_to'),
        ];

        if ($includeOutletName) {
            $selects[] = DB::raw("COALESCE(master.nama_outlet, so.id_outlet) as outlet_name");
        } else {
            $selects[] = 'so.id_outlet as outlet_name';
        }

        $selects[] = $includeUser
            ? 'ma.name as user_name'
            : DB::raw('NULL as user_name');

        return $query->select($selects);
    }

    private function wrapAsSellout(Builder $query): Builder
    {
        return DB::connection(self::CONN_DWH)
            ->query()
            ->fromSub($query, 'so');
    }

    public function baseQuery(
        DashboardFilterRequest $request,
        ?array $dateRange = null,
        ?array $months = null,
        ?string $coverageId = null
    ): Builder {
        $range = $dateRange ?? [$request->startDate(), $request->endDate()];

        return $this->wrapAsSellout(
            $this->enrichedSummaryQuery($request, $range, true, true, false, $months, $coverageId)
        );
    }

    public function baseChartQuery(
        DashboardFilterRequest $request,
        ?array $dateRange = null,
        ?array $months = null,
        ?string $coverageId = null
    ): Builder {
        $range = $dateRange ?? [$request->startDate(), $request->endDate()];

        return $this->wrapAsSellout(
            $this->enrichedSummaryQuery($request, $range, false, false, true, $months, $coverageId)
        );
    }

    public function drilldownAggregateQuery(
        DashboardFilterRequest $request,
        string $levelNum,
        ?string $parentId = null
    ): Builder {
        [$start, $end] = $this->normalizeDateRange([$request->startDate(), $request->endDate()]);
        $levelDepth = max((int) $levelNum, 1);
        $coverageSql = 'mo.id_coverage';
        $parentCoverageSql = "NULLIF(SUBSTRING_INDEX({$coverageSql}, '.', {$levelDepth}), '')";

        $query = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_cust_cov') . ' as mo'), function ($join) {
                $join->on('so.id_konversi', '=', 'mo.id_konversi')
                    ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mo.pilar'));
            })
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_mappings') . ' as mc'), function ($join) use ($parentCoverageSql, $levelNum) {
                $join->on('mc.id_coverage', '=', DB::raw($parentCoverageSql))
                    ->where('mc.cov_level', $levelNum)
                    ->whereNull('mc.deleted_at');

                $this->applyMappingValidity($join, 'mc');
            })
            ->leftJoin(DB::raw($this->authTable('users') . ' as ma'), 'mc.cov_nik', '=', 'ma.nik')
            ->whereBetween('so.tgl_faktur', [$start, $end]);

        $user = Auth::user();
        if (!$this->coverageAccess->isGlobalAccessUser($user)) {
            $roots = $this->coverageAccess->rootCoverages($user);

            if (empty($roots)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($coverageSql, $roots) {
                    foreach ($roots as $root) {
                        $q->orWhereRaw($coverageSql . ' LIKE ?', [$root . '%']);
                    }
                });
            }
        }

        if ($parentId && $parentId !== 'UNMAPPED') {
            $query->whereRaw($parentCoverageSql . ' LIKE ?', [$parentId . '.%']);
        }

        $this->applyGlobalFilters($query, $request, 'so.pilar_sub', 'so.dist', 'so.code_prod_ph');

        return $query->selectRaw("
                COALESCE(mc.id_coverage, 'UNMAPPED') as coverage_id,
                COALESCE(MAX(mc.cov_desc), 'Data Belum Termapping') as coverage_desc,
                COALESCE(MAX(mc.cov_level), ?) as coverage_level,
                COALESCE(MAX(mc.cov_pilar), 'N/A') as coverage_pilar,
                MAX(mc.cov_divisi) as coverage_divisi,
                COALESCE(MAX(mc.cov_nik), '-') as coverage_nik,
                COALESCE(MAX(ma.name), 'N/A') as user_name,
                SUM(so.netto) as total_netto,
                COUNT(DISTINCT so.id_outlet) as total_outlet,
                COUNT(DISTINCT CONCAT(so.source_table, '|', so.source_id)) as total_transaksi,
                (CASE WHEN mc.id_coverage IS NULL THEN 1 ELSE 0 END) as is_unmapped
            ", [$levelNum])
            ->groupBy([
                'mc.id_coverage',
                DB::raw("(CASE WHEN mc.id_coverage IS NULL THEN 1 ELSE 0 END)"),
            ]);
    }

    private function topMarketingBaseQuery(DashboardFilterRequest $request): Builder
    {
        $startMonth = Carbon::parse($request->startDate())->startOfMonth()->toDateString();
        $endMonth = Carbon::parse($request->endDate())->startOfMonth()->toDateString();

        $query = DB::connection(self::CONN_DWH)
            ->table('crm_top_marketing as top')
            ->whereRaw(
                "STR_TO_DATE(CONCAT(top.tahun, '-', LPAD(top.bulan, 2, '0'), '-01'), '%Y-%m-%d') BETWEEN ? AND ?",
                [$startMonth, $endMonth]
            );

        $this->applyGlobalFilters($query, $request, 'top.pilar', null, 'top.kode_ph');

        return $query;
    }

    public function topMarketingTarget(DashboardFilterRequest $request): float
    {
        return (float) $this->topMarketingBaseQuery($request)->sum('top.val_net');
    }

    public function topMarketingByRegion(DashboardFilterRequest $request)
    {
        return $this->topMarketingBaseQuery($request)
            ->selectRaw("
                COALESCE(NULLIF(TRIM(top.divisi), ''), 'LAINNYA') as divisi,
                COALESCE(NULLIF(TRIM(top.region), ''), 'LAINNYA') as region,
                SUM(top.val_net) as top_value
            ")
            ->groupBy('divisi', 'region')
            ->get();
    }

    public function realNettoByRegion(DashboardFilterRequest $request)
    {
        [$start, $end] = $this->normalizeDateRange([$request->startDate(), $request->endDate()]);
        $regionSql = "CASE
            WHEN UPPER(TRIM(so.regional)) LIKE 'OTC%' THEN 'OTC'
            ELSE COALESCE(NULLIF(TRIM(so.regional), ''), 'LAINNYA')
        END";

        $query = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_cust_cov') . ' as mo'), function ($join) {
                $join->on('so.id_konversi', '=', 'mo.id_konversi')
                    ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mo.pilar'));
            })
            ->whereBetween('so.tgl_faktur', [$start, $end]);

        $query = $this->coverageAccess->applyScope($query, 'mo.id_coverage');

        $this->applyGlobalFilters($query, $request, 'so.pilar_sub', 'so.dist', 'so.code_prod_ph');

        return $query
            ->selectRaw("
                COALESCE(NULLIF(TRIM(so.divisi), ''), 'LAINNYA') as divisi,
                {$regionSql} as region,
                SUM(so.netto) as real_value
            ")
            ->groupBy('divisi', DB::raw($regionSql))
            ->get();
    }

    public function outletHistoryAggregateQuery(DashboardFilterRequest $request, ?string $search = null): Builder
    {
        $months = $request->bulanList();
        [$start, $end] = $this->outletHistoryDateRange($request);
        $hasSearch = trim((string) $search) !== '';

        $transactions = $this->enrichedSummaryQuery(
            $request,
            [$start, $end],
            $hasSearch,
            $hasSearch,
            false,
            count($months) > 1 ? $months : null,
            $request->get('coverage_id')
        );

        if ($hasSearch) {
            $this->applyHistorySearch($transactions, (string) $search);
        }

        $query = $this->wrapAsSellout($transactions);

        if (!$hasSearch) {
            $query->leftJoin(
                DB::raw($this->dwhTable('crm_mst_cust') . ' as vcd'),
                'so.id_konversi',
                '=',
                'vcd.id_konversi'
            )
                ->leftJoin(
                    DB::raw($this->authTable('users') . ' as ma'),
                    'so.coverage_nik',
                    '=',
                    'ma.nik'
                );
        }

        $outletNameSql = $hasSearch
            ? 'so.outlet_name'
            : 'COALESCE(vcd.nama_outlet, so.id_outlet)';
        $userNameSql = $hasSearch ? 'so.user_name' : 'ma.name';

        return $query
            ->selectRaw("
                so.dist,
                so.id_outlet,
                {$outletNameSql} as outlet_name,
                so.coverage_id,
                so.coverage_desc,
                so.coverage_nik,
                {$userNameSql} as user_name,
                so.coverage_level,
                so.coverage_pilar,
                so.product_pilar,
                so.coverage_valid_from,
                so.coverage_valid_to,
                MIN(so.tgl_faktur) as bulan_date,
                COUNT(so.source_id) as total_transaksi,
                SUM(so.netto) as total_netto
            ")
            ->groupBy(
                'so.dist',
                'so.id_outlet',
                DB::raw($outletNameSql),
                'so.coverage_id',
                'so.coverage_desc',
                'so.coverage_nik',
                DB::raw($userNameSql),
                'so.coverage_level',
                'so.coverage_pilar',
                'so.product_pilar',
                'so.coverage_valid_from',
                'so.coverage_valid_to',
                DB::raw('YEAR(so.tgl_faktur)'),
                DB::raw('MONTH(so.tgl_faktur)')
            );
    }

    public function cacheKey(string $prefix, DashboardFilterRequest $request, ?string $extra = null): string
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        $userKey = $user
            ? ($user->id . '|' . $user->nik . '|' . $user->getRoleNames()->sort()->implode(','))
            : 'guest';

        $params = [
            self::CACHE_VERSION,
            $userKey,
            $request->startDate(),
            $request->endDate(),
            $request->get('level', ''),
            json_encode($request->get('pilar', [])),
            json_encode($request->get('distributor', [])),
            json_encode($request->get('product', [])),
            $request->get('coverage_id', ''),
            $extra ?? '',
        ];

        return 'dashboard:' . $prefix . ':' . md5(implode('|', $params));
    }

    public function cacheTtl(): int
    {
        return self::CACHE_TTL;
    }

    public function levelToNum(string $level): string
    {
        return self::LEVEL_MAP[$level] ?? $level;
    }

    public function numToLevel(?string $num): string
    {
        if (is_null($num)) {
            return 'Unmapped';
        }

        return array_flip(self::LEVEL_MAP)[$num] ?? 'Unknown';
    }

    public function nextLevel(string $level): ?string
    {
        $idx = array_search($level, self::LEVEL_ORDER);
        return self::LEVEL_ORDER[$idx + 1] ?? null;
    }

    public function applyGlobalFilters(Builder $query, DashboardFilterRequest $request, string $pilarCol = 'so.pilar_sub', ?string $distCol = 'so.dist', ?string $prodCol = 'so.code_prod_ph'): void
    {
        if ($request->filled('pilar')) {
            $query->whereIn(
                $pilarCol,
                collect($request->input('pilar'))
                    ->map(fn($pilar) => $this->normalizePilarValue($pilar))
                    ->unique()
                    ->values()
                    ->all()
            );
        }

        if ($distCol && $request->filled('distributor')) {
            $query->whereIn($distCol, $request->input('distributor'));
        }

        if ($prodCol && $request->filled('product')) {
            $query->whereIn($prodCol, $request->input('product'));
        }
    }

    private function baseAnggotaDetailQuery(string $nik, Carbon $start, Carbon $end, array $outletIds): Builder
    {
        return DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->whereIn('so.id_outlet', $outletIds)
            ->join('crm_mst_cust_cov as mo', function ($join) {
                $join->on('so.id_konversi', '=', 'mo.id_konversi')
                     ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mo.pilar'));
            })
            ->leftJoin('crm_mst_mappings as mc', function ($join) {
                $join->on('mo.id_coverage', '=', 'mc.id_coverage')
                    ->whereNull('mc.deleted_at')
                    ->where(function ($q) {
                        $q->whereNull('mc.valid_from')->orWhereRaw('so.tgl_faktur >= mc.valid_from');
                    })
                    ->where(function ($q) {
                        $q->whereNull('mc.valid_to')->orWhereRaw('so.tgl_faktur <= mc.valid_to');
                    })->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mc.cov_pilar'));
            })
            ->where('mc.cov_nik', $nik)
            ->whereBetween('so.tgl_faktur', [$start, $end]);
    }

    public function anggotaDetailProducts(string $nik, Carbon $start, Carbon $end, array $outletIds): \Illuminate\Support\Collection
    {
        $query = $this->baseAnggotaDetailQuery($nik, $start, $end, $outletIds);
        return $query
            ->leftJoin('crm_mst_cust as master', 'so.id_konversi', '=', 'master.id_konversi')
            ->selectRaw('
                so.id_outlet as id_outlet,
                COALESCE(MAX(master.nama_outlet), so.id_outlet) as outlet_name,
                COALESCE(NULLIF(so.code_product, ""), NULLIF(so.code_prod_ph, ""), "-") as sku,
                COALESCE(NULLIF(so.`desc`, ""), so.code_product, so.code_prod_ph, "-") as product_name,
                SUM(so.netto) as netto
            ')
            ->groupBy('so.id_outlet', 'sku', 'product_name')
            ->orderByDesc('netto')
            ->get();
    }

    public function anggotaDetailTrend(string $nik, Carbon $start, Carbon $end, array $outletIds): \Illuminate\Support\Collection
    {
        return $this->baseAnggotaDetailQuery($nik, $start, $end, $outletIds)
            ->selectRaw("DATE_FORMAT(so.tgl_faktur, '%Y-%m') as periode, SUM(so.netto) as total_netto")
            ->groupBy('periode')
            ->orderBy('periode')
            ->pluck('total_netto', 'periode');
    }

    public function anggotaMappedOutlets(string $nik): array
    {
        return DB::connection(self::CONN_DWH)
            ->table('crm_mst_mappings as mc')
            ->join('crm_mst_cust_cov as mo', function ($join) {
                $join->on('mc.id_coverage', '=', 'mo.id_coverage');
            })
            ->join('crm_mst_cust_dist as dist', 'mo.id_konversi', '=', 'dist.id_konversi')
            ->where('mc.cov_nik', $nik)
            ->whereNull('mc.deleted_at')
            ->pluck('dist.id_outlet')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
