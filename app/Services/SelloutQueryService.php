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
    private const CACHE_VERSION = 'v11';
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
        'GM' => '1',
        'RM' => '2',
        'SAM' => '3',
        'AM' => '4',
        'MR' => '5',
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

    private function customerOutletNameQuery(): Builder
    {
        return DB::connection(self::CONN_DWH)
            ->table('mst_cust_dist')
            ->selectRaw('id_outlet, id_cbg_dist, MAX(nama_outlet) as outlet_name')
            ->groupBy('id_outlet', 'id_cbg_dist');
    }

    private function normalizedPilarSql(string $column): string
    {
        return "CASE
            WHEN UPPER(TRIM({$column})) = 'ETHICOM' THEN 'ETHICOM'
            WHEN UPPER(TRIM({$column})) = 'ETHIPROM' THEN 'ETHIPROM'
            WHEN UPPER(TRIM({$column})) IN ('OGB', 'GENERIK') THEN 'OGB'
            WHEN UPPER(TRIM({$column})) = 'OTC' THEN 'OTC'
            ELSE UPPER(TRIM({$column}))
        END";
    }

    private function pilarMatchSql(string $productPilarCol, string $mappingPilarCol): string
    {
        $prod = $this->normalizedPilarSql($productPilarCol);
        $map = $this->normalizedPilarSql($mappingPilarCol);

        return "({$prod} = {$map} OR ({$map} = 'ETHICAL' AND {$prod} IN ('ETHICOM', 'ETHIPROM')))";
    }

    private function normalizePilarValue(?string $pilar): ?string
    {
        if ($pilar === null || $pilar === '') {
            return null;
        }

        $pilar = strtoupper(trim($pilar));

        switch ($pilar) {
            case 'ETHICOM':
                return 'ETHICOM';
            case 'ETHIPROM':
                return 'ETHIPROM';
            case 'GENERIK':
                return 'OGB';
            default:
                return $pilar;
        }
    }

    // -------------------------------------------------------
    // STANDARD JOINS - Query langsung dari summary table
    // -------------------------------------------------------
    private function applyStandardJoins($query)
    {
        return $query
            ->leftJoin(DB::raw($this->dwhTable('mst_cust_dist') . ' as vcd'), function ($join) {
                $join->on('so.id_outlet', '=', 'vcd.id_outlet')
                    ->on('so.id_cbg', '=', 'vcd.id_cbg_dist');
            })
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as mo'), function ($join) {
                $join->on('so.id_outlet', '=', 'mo.id_outlet')
                    ->whereNull('mo.deleted_at');

                $join->whereExists(function ($exists) {
                    $exists->select(DB::raw(1))
                        ->from(DB::raw($this->dwhTable('crm_mst_mappings') . ' as mx'))
                        ->whereColumn('mx.id_coverage', 'mo.id_coverage')
                        ->whereNull('mx.deleted_at')
                        ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mx.cov_pilar'));
                });
            });
    }

    /**
     * Join minimal untuk chart - tanpa users join
     */
    private function applyChartJoins($query)
    {
        return $query
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as mo'), function ($join) {
                $join->on('so.id_outlet', '=', 'mo.id_outlet')
                    ->whereNull('mo.deleted_at');

                $join->whereExists(function ($exists) {
                    $exists->select(DB::raw(1))
                        ->from(DB::raw($this->dwhTable('crm_mst_mappings') . ' as mx'))
                        ->whereColumn('mx.id_coverage', 'mo.id_coverage')
                        ->whereNull('mx.deleted_at')
                        ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mx.cov_pilar'));
                });
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

    private function applyMatchingMappingExists(
        $query,
        string $alias = 'mx',
        ?string $levelNum = null,
        string $outletAlias = 'mo'
    ): void {
        $query->whereExists(function ($exists) use ($alias, $levelNum, $outletAlias) {
            $exists->select(DB::raw(1))
                ->from(DB::raw($this->dwhTable('crm_mst_mappings') . " as {$alias}"))
                ->whereColumn("{$alias}.id_coverage", "{$outletAlias}.id_coverage")
                ->whereNull("{$alias}.deleted_at")
                ->whereRaw($this->pilarMatchSql('so.pilar_sub', "{$alias}.cov_pilar"));

            if ($levelNum !== null) {
                $exists->where("{$alias}.cov_level", $levelNum);
            }

            $this->applyMappingValidity($exists, $alias);
        });
    }

    private function applyMonthlyOutletExists(Builder $query, DashboardFilterRequest $request): void
    {
        $user = Auth::user();
        $isScopedUser = $user && !$this->coverageAccess->isGlobalAccessUser($user);
        $roots = $isScopedUser ? $this->coverageAccess->rootCoverages($user) : [];

        if ($isScopedUser && empty($roots)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $levelNum = $request->filled('level')
            ? $this->levelToNum($request->get('level'))
            : null;

        $query->whereExists(function ($exists) use ($request, $roots, $levelNum) {
            $exists->select(DB::raw(1))
                ->from(DB::raw($this->dwhTable('crm_mst_outlets') . ' as mo_filter'))
                ->whereColumn('mo_filter.id_outlet', 'so.id_outlet')
                ->whereNull('mo_filter.deleted_at');

            if (!empty($roots)) {
                $exists->where(function ($q) use ($roots) {
                    foreach ($roots as $root) {
                        $q->orWhere('mo_filter.id_coverage', 'like', $root . '%');
                    }
                });
            }

            if ($request->filled('coverage_id')) {
                $exists->where('mo_filter.id_coverage', $request->get('coverage_id'));
            }

            $this->applyMatchingMappingExists($exists, 'mm_filter', $levelNum, 'mo_filter');
        });
    }

    private function monthlyChartNeedsOutletFilter(DashboardFilterRequest $request): bool
    {
        $user = Auth::user();

        return ($user && !$this->coverageAccess->isGlobalAccessUser($user))
            || $request->filled('level')
            || $request->filled('coverage_id');
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

    private function applyMonthListFilter(Builder $query, array $months, int $year): void
    {
        if (count($months) <= 1) {
            return;
        }

        $query->where(function ($q) use ($months, $year) {
            foreach ($months as $month) {
                $q->orWhereBetween('so.tgl_faktur', [
                    Carbon::create($year, (int) $month, 1)->startOfMonth()->toDateTimeString(),
                    Carbon::create($year, (int) $month, 1)->endOfMonth()->toDateTimeString(),
                ]);
            }
        });
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

        $query = $forChart
            ? $this->applyChartJoins($query)
            : $this->applyStandardJoins($query);

        $query->leftJoin(
            DB::raw($this->dwhTable('crm_mst_mappings') . ' as mc'),
            $this->mappingJoinCallback()
        );

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

        $query = $this->coverageAccess->applyScope($query, 'mo.id_coverage');

        if ($request->filled('level')) {
            $query->where('mc.cov_level', $this->levelToNum($request->get('level')));
        }

        if ($request->filled('pilar')) {
            $query->whereIn(
                DB::raw($this->normalizedPilarSql('so.pilar_sub')),
                collect($request->input('pilar'))
                    ->map(fn($pilar) => $this->normalizePilarValue($pilar))
                    ->unique()
                    ->values()
                    ->all()
            );
        }

        if ($request->filled('distributor')) {
            $query->whereIn('so.dist', $request->input('distributor'));
        }

        if ($request->filled('product')) {
            $query->whereIn('so.code_prod_ph', $request->input('product'));
        }

        $selects = [
            'so.id',
            'so.source_table',
            'so.source_id',
            'so.tgl_faktur',
            'so.dist',
            'so.id_outlet',
            'so.id_cbg',
            'so.code_product',
            'so.segment_mi',
            'so.netto',
            'so.pilar_sub as product_pilar',
            'mc.id_coverage as coverage_id',
            'mc.cov_desc as coverage_desc',
            'mc.cov_nik as coverage_nik',
            'mc.cov_level as coverage_level',
            'mc.cov_pilar as coverage_pilar',
            'mc.cov_divisi as coverage_divisi',
            'mc.valid_from as coverage_valid_from',
            'mc.valid_to as coverage_valid_to',
        ];

        if ($includeOutletName) {
            $selects[] = DB::raw("COALESCE(vcd.nama_outlet, so.id_outlet) as outlet_name");
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

    /**
     * Base Query untuk dashboard
     * Query dari crm_sellout_summary (DWH) + join ke master tables (DWH) + cross-DB join ke users (MySQL)
     */
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
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as mo'), function ($join) {
                $join->on('so.id_outlet', '=', 'mo.id_outlet')
                    ->whereNull('mo.deleted_at')
                    ->whereExists(function ($exists) {
                        $exists->select(DB::raw(1))
                            ->from(DB::raw($this->dwhTable('crm_mst_mappings') . ' as mx'))
                            ->whereColumn('mx.id_coverage', 'mo.id_coverage')
                            ->whereNull('mx.deleted_at')
                            ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mx.cov_pilar'));

                        $this->applyMappingValidity($exists, 'mx');
                    });
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

        if ($request->filled('pilar')) {
            $query->whereIn(
                DB::raw($this->normalizedPilarSql('so.pilar_sub')),
                collect($request->input('pilar'))
                    ->map(fn($pilar) => $this->normalizePilarValue($pilar))
                    ->unique()
                    ->values()
                    ->all()
            );
        }

        if ($request->filled('distributor')) {
            $query->whereIn('so.dist', $request->input('distributor'));
        }

        if ($request->filled('product')) {
            $query->whereIn('so.code_prod_ph', $request->input('product'));
        }

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

    /**
     * Base Query untuk trend per tahun (12 bulan)
     */
    public function baseQueryByYear(DashboardFilterRequest $request): Builder
    {
        $year = (int) $request->tahun();
        $yearRange = [
            Carbon::create($year, 1, 1, 0, 0, 0)->toDateString(),
            Carbon::create($year, 12, 31, 23, 59, 59)->toDateString(),
        ];

        return $this->baseQuery($request, $yearRange);
    }

    /**
     * Lightweight Base Query untuk chart aggregation
     * Tanpa join ke users, hanya fokus pada data transaksi
     */
    public function baseChartQuery(
        DashboardFilterRequest $request,
        ?array $dateRange = null
    ): Builder {
        $range = $dateRange ?? [$request->startDate(), $request->endDate()];

        return $this->wrapAsSellout(
            $this->enrichedSummaryQuery($request, $range, false, false, true)
        );
    }

    /**
     * Base Chart Query per tahun
     */
    public function baseChartQueryByYear(DashboardFilterRequest $request): Builder
    {
        $year = (int) $request->tahun();
        $yearRange = [
            Carbon::create($year, 1, 1, 0, 0, 0)->toDateString(),
            Carbon::create($year, 12, 31, 23, 59, 59)->toDateString(),
        ];

        return $this->baseChartQuery($request, $yearRange);
    }

    public function topMarketingTarget(DashboardFilterRequest $request): float
    {
        $startMonth = Carbon::parse($request->startDate())->startOfMonth()->toDateString();
        $endMonth = Carbon::parse($request->endDate())->startOfMonth()->toDateString();

        $query = DB::connection(self::CONN_DWH)
            ->table('crm_top_marketing as top')
            ->whereRaw(
                "STR_TO_DATE(CONCAT(top.tahun, '-', LPAD(top.bulan, 2, '0'), '-01'), '%Y-%m-%d') BETWEEN ? AND ?",
                [$startMonth, $endMonth]
            );

        if ($request->filled('pilar')) {
            $query->whereIn(
                DB::raw($this->normalizedPilarSql('top.pilar')),
                collect($request->input('pilar'))
                    ->map(fn($pilar) => $this->normalizePilarValue($pilar))
                    ->unique()
                    ->values()
                    ->all()
            );
        }

        if ($request->filled('product')) {
            $query->whereIn('top.kode_ph', $request->input('product'));
        }

        return (float) $query->sum('top.val_net');
    }

    public function topMarketingByRegion(DashboardFilterRequest $request)
    {
        $startMonth = Carbon::parse($request->startDate())->startOfMonth()->toDateString();
        $endMonth = Carbon::parse($request->endDate())->startOfMonth()->toDateString();

        $query = DB::connection(self::CONN_DWH)
            ->table('crm_top_marketing as top')
            ->whereRaw(
                "STR_TO_DATE(CONCAT(top.tahun, '-', LPAD(top.bulan, 2, '0'), '-01'), '%Y-%m-%d') BETWEEN ? AND ?",
                [$startMonth, $endMonth]
            );

        if ($request->filled('pilar')) {
            $query->whereIn(
                DB::raw($this->normalizedPilarSql('top.pilar')),
                collect($request->input('pilar'))
                    ->map(fn($pilar) => $this->normalizePilarValue($pilar))
                    ->unique()
                    ->values()
                    ->all()
            );
        }

        if ($request->filled('product')) {
            $query->whereIn('top.kode_ph', $request->input('product'));
        }

        return $query
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
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as mo'), function ($join) {
                $join->on('so.id_outlet', '=', 'mo.id_outlet')
                    ->whereNull('mo.deleted_at');
            })
            ->whereBetween('so.tgl_faktur', [$start, $end]);

        $query = $this->coverageAccess->applyScope($query, 'mo.id_coverage');

        if ($request->filled('pilar')) {
            $query->whereIn(
                DB::raw($this->normalizedPilarSql('so.pilar_sub')),
                collect($request->input('pilar'))
                    ->map(fn($pilar) => $this->normalizePilarValue($pilar))
                    ->unique()
                    ->values()
                    ->all()
            );
        }

        if ($request->filled('distributor')) {
            $query->whereIn('so.dist', $request->input('distributor'));
        }

        if ($request->filled('product')) {
            $query->whereIn('so.code_prod_ph', $request->input('product'));
        }

        return $query
            ->selectRaw("
                COALESCE(NULLIF(TRIM(so.divisi), ''), 'LAINNYA') as divisi,
                {$regionSql} as region,
                SUM(so.netto) as real_value
            ")
            ->groupBy('divisi', DB::raw($regionSql))
            ->get();
    }

    /**
     * Query khusus chart bulanan.
     * Tetap menghormati mapping/access filter, tapi tidak melakukan enrichment per transaksi.
     */
    public function monthlyChartQuery(DashboardFilterRequest $request): Builder
    {
        [$start, $end] = $this->normalizeDateRange([$request->startDate(), $request->endDate()]);
        $query = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->whereBetween('so.tgl_faktur', [$start, $end]);

        if ($this->monthlyChartNeedsOutletFilter($request)) {
            $this->applyMonthlyOutletExists($query, $request);
        }

        if ($request->filled('pilar')) {
            $query->whereIn(
                DB::raw($this->normalizedPilarSql('so.pilar_sub')),
                collect($request->input('pilar'))
                    ->map(fn($pilar) => $this->normalizePilarValue($pilar))
                    ->unique()
                    ->values()
                    ->all()
            );
        }

        if ($request->filled('distributor')) {
            $query->whereIn('so.dist', $request->input('distributor'));
        }

        if ($request->filled('product')) {
            $query->whereIn('so.code_prod_ph', $request->input('product'));
        }

        return $query
            ->selectRaw("DATE_FORMAT(so.tgl_faktur, '%Y-%m') as periode, SUM(so.netto) as total_netto")
            ->groupByRaw("DATE_FORMAT(so.tgl_faktur, '%Y-%m')");
    }

    /**
     * Query untuk outlet history - menampilkan perkembangan transaksi per outlet per bulan
     */
    public function outletHistoryQuery(DashboardFilterRequest $request)
    {
        $months = $request->bulanList();
        $coverageId = $request->get('coverage_id');

        if (count($months) > 1) {
            $year = (int) $request->tahun();
            $firstMonth = min($months);
            $lastMonth = max($months);

            $query = $this->baseQuery($request, [
                Carbon::create($year, $firstMonth, 1)->startOfMonth()->toDateString(),
                Carbon::create($year, $lastMonth, 1)->endOfMonth()->toDateString(),
            ], $months, $coverageId);
        } else {
            $query = $this->baseQuery($request, null, null, $coverageId);
        }

        return $query;
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
            $query->leftJoinSub($this->customerOutletNameQuery(), 'vcd', function ($join) {
                $join->on('so.id_outlet', '=', 'vcd.id_outlet')
                    ->on('so.id_cbg', '=', 'vcd.id_cbg_dist');
            })
                ->leftJoin(
                    DB::raw($this->authTable('users') . ' as ma'),
                    'so.coverage_nik',
                    '=',
                    'ma.nik'
                );
        }

        $outletNameSql = $hasSearch
            ? 'so.outlet_name'
            : 'COALESCE(vcd.outlet_name, so.id_outlet)';
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

    /**
     * Helper: Hitung grouped result set
     */
    public function countGrouped(Builder $query): int
    {
        return DB::connection(self::CONN_DWH)
            ->query()
            ->fromSub($query, 'agg')
            ->count();
    }

    /**
     * Generate cache key untuk dashboard queries
     */
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

    public function yearlyCacheKey(string $prefix, DashboardFilterRequest $request, ?string $extra = null): string
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        $userKey = $user
            ? ($user->id . '|' . $user->nik . '|' . $user->getRoleNames()->sort()->implode(','))
            : 'guest';

        $params = [
            self::CACHE_VERSION,
            $userKey,
            $request->tahun(),
            $request->get('level', ''),
            json_encode($request->get('pilar', [])),
            json_encode($request->get('distributor', [])),
            json_encode($request->get('product', [])),
            $request->get('coverage_id', ''),
            $extra ?? '',
        ];

        return 'dashboard:' . $prefix . ':' . md5(implode('|', $params));
    }

    /**
     * TTL untuk cache
     */
    public function cacheTtl(): int
    {
        return self::CACHE_TTL;
    }

    /**
     * Convert level string ke number
     */
    public function levelToNum(string $level): string
    {
        return self::LEVEL_MAP[$level] ?? $level;
    }

    /**
     * Convert level number ke string
     */
    public function numToLevel(?string $num): string
    {
        if (is_null($num)) {
            return 'Unmapped';
        }

        return array_flip(self::LEVEL_MAP)[$num] ?? 'Unknown';
    }

    /**
     * Get next level dalam hierarchy
     */
    public function nextLevel(string $level): ?string
    {
        $idx = array_search($level, self::LEVEL_ORDER);
        return self::LEVEL_ORDER[$idx + 1] ?? null;
    }
}
