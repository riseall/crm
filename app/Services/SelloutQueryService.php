<?php

namespace App\Services;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SelloutQueryService
{
    private const CONN_DWH = 'db_dwh';
    private const CONN_AUTH = 'mysql';
    private const CACHE_TTL = 300;
    private const CACHE_VERSION = 'v21';
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

        $isUnmappedOnly = ($coverageId === 'UNMAPPED' || $request->get('coverage_id') === 'UNMAPPED');

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
        } elseif ($isUnmappedOnly) {
            $query->where(function ($q) {
                $q->whereNull('mc.id_coverage')
                  ->orWhereNull('mo.id_coverage');
            });
        } elseif ($coverageId !== null && trim($coverageId) !== '') {
            $query->where('mc.id_coverage', $coverageId);
        }

        if ($request->filled('outlet_id')) {
            $query->where('so.id_outlet', $request->get('outlet_id'));
        }

        if (!$forChart && !$isUnmappedOnly) {
            $query = $this->coverageAccess->applyScope($query, 'mo.id_coverage');
        }

        if ($request->filled('level') && !$isUnmappedOnly) {
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
            'so.divisi',
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
            $selects[] = DB::raw("COALESCE(NULLIF(TRIM(master.nama_outlet), ''), NULLIF(TRIM(so.nama_outlet), ''), so.id_outlet) as outlet_name");
        } else {
            $selects[] = DB::raw("COALESCE(NULLIF(TRIM(so.nama_outlet), ''), so.id_outlet) as outlet_name");
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
        [$start, $end] = $this->normalizeDateRange($range);

        $query = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so');

        $isUnmappedOnly = ($coverageId === 'UNMAPPED' || $request->get('coverage_id') === 'UNMAPPED');

        $user = auth()->user();
        $needsOutletFilter = ($user && !$this->coverageAccess->isGlobalAccessUser($user))
            || $request->filled('level')
            || $request->filled('coverage_nik')
            || ($coverageId !== null && trim($coverageId) !== '');

        if ($needsOutletFilter) {
            $query = $this->applyChartJoins($query);
        }

        $needsMappingJoin = $request->filled('level')
            || $request->filled('coverage_nik')
            || ($coverageId !== null && trim($coverageId) !== '');

        if ($needsMappingJoin) {
            $query->leftJoin(
                DB::raw($this->dwhTable('crm_mst_mappings') . ' as mc'),
                $this->mappingJoinCallback()
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
        } elseif ($isUnmappedOnly) {
            $query->where(function ($q) {
                $q->whereNull('mc.id_coverage')
                  ->orWhereNull('mo.id_coverage');
            });
        } elseif ($coverageId !== null && trim($coverageId) !== '') {
            $query->where('mc.id_coverage', $coverageId);
        }

        if ($request->filled('outlet_id')) {
            $query->where('so.id_outlet', $request->get('outlet_id'));
        }

        if ($request->filled('level') && !$isUnmappedOnly) {
            $query->where('mc.cov_level', $this->levelToNum($request->get('level')));
        }

        $this->applyGlobalFilters($query, $request, 'so.pilar_sub', 'so.dist', 'so.code_prod_ph');

        return $query;
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

    public function applyTopMarketingScope(Builder $query, DashboardFilterRequest $request): void
    {
        $user = auth()->user();
        if ($user && !$this->coverageAccess->isGlobalAccessUser($user)) {
            $roots = $this->coverageAccess->rootCoverages($user);

            if (empty($roots)) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where(function ($q) use ($roots) {
                foreach ($roots as $root) {
                    $q->orWhere('top.mr', 'like', $root . '%')
                      ->orWhere('top.am', $root)
                      ->orWhere('top.sam', $root)
                      ->orWhere('top.rm', $root)
                      ->orWhere('top.gm', $root);
                }
            });
        }

        if ($request->filled('coverage_id') && $request->get('coverage_id') !== 'UNMAPPED') {
            $covId = $request->get('coverage_id');
            $query->where(function ($q) use ($covId) {
                $q->where('top.mr', 'like', $covId . '%')
                  ->orWhere('top.am', $covId)
                  ->orWhere('top.sam', $covId)
                  ->orWhere('top.rm', $covId)
                  ->orWhere('top.gm', $covId);
            });
        }

        if ($request->filled('coverage_nik')) {
            $nik = $request->get('coverage_nik');
            $nikCoverages = DB::connection(self::CONN_DWH)
                ->table('crm_mst_mappings')
                ->where('cov_nik', $nik)
                ->whereNull('deleted_at')
                ->pluck('id_coverage')
                ->filter()
                ->all();

            if (!empty($nikCoverages)) {
                $query->where(function ($q) use ($nikCoverages) {
                    foreach ($nikCoverages as $covId) {
                        $q->orWhere('top.mr', 'like', $covId . '%')
                          ->orWhere('top.am', $covId)
                          ->orWhere('top.sam', $covId)
                          ->orWhere('top.rm', $covId)
                          ->orWhere('top.gm', $covId);
                    }
                });
            }
        }
    }

    public function topMarketingBaseQuery(DashboardFilterRequest $request, bool $applyScope = true): Builder
    {
        $startMonth = Carbon::parse($request->startDate())->startOfMonth()->toDateString();
        $endMonth = Carbon::parse($request->endDate())->startOfMonth()->toDateString();

        $query = DB::connection(self::CONN_DWH)
            ->table('crm_top_marketing as top')
            ->whereRaw(
                "STR_TO_DATE(CONCAT(top.tahun, '-', LPAD(top.bulan, 2, '0'), '-01'), '%Y-%m-%d') BETWEEN ? AND ?",
                [$startMonth, $endMonth]
            );

        if ($applyScope) {
            $this->applyTopMarketingScope($query, $request);
        }

        $this->applyGlobalFilters($query, $request, 'top.pilar', null, 'top.kode_ph');

        return $query;
    }

    public function topMarketingTarget(DashboardFilterRequest $request): float
    {
        return (float) $this->topMarketingBaseQuery($request)->sum('top.val_net');
    }

    public function topMarketingByLevel(DashboardFilterRequest $request, string $levelName): \Illuminate\Support\Collection
    {
        $levelCol = strtolower(trim($levelName));
        if (!in_array($levelCol, ['gm', 'rm', 'sam', 'am', 'mr', 'pm'], true)) {
            $levelCol = 'mr';
        }

        return $this->topMarketingBaseQuery($request)
            ->whereNotNull("top.{$levelCol}")
            ->where("top.{$levelCol}", '!=', '')
            ->selectRaw("
                TRIM(top.{$levelCol}) as coverage_id,
                SUM(top.val_net) as total_top
            ")
            ->groupBy(DB::raw("TRIM(top.{$levelCol})"))
            ->pluck('total_top', 'coverage_id');
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

        $user = auth()->user();
        $isGlobal = $user && $this->coverageAccess->isGlobalAccessUser($user);

        $query = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->whereBetween('so.tgl_faktur', [$start, $end]);

        if (!$isGlobal) {
            $query->whereExists(function ($exists) {
                $exists->select(DB::raw(1))
                    ->from(DB::raw($this->dwhTable('crm_mst_cust_cov') . ' as mo'))
                    ->whereColumn('mo.id_konversi', 'so.id_konversi')
                    ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mo.pilar'));

                $this->coverageAccess->applyScope($exists, 'mo.id_coverage');
            });
        }

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

    private function applyHistorySearch(Builder $query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('so.id_outlet', 'like', "%{$search}%")
                ->orWhere('so.nama_outlet', 'like', "%{$search}%")
                ->orWhere('so.id_cbg', 'like', "%{$search}%")
                ->orWhere('master.nama_outlet', 'like', "%{$search}%")
                ->orWhere('ma.name', 'like', "%{$search}%")
                ->orWhere('mc.cov_nik', 'like', "%{$search}%");
        });
    }

    public function outletHistoryAggregateQuery(DashboardFilterRequest $request, ?string $search = null): Builder
    {
        $months = $request->bulanList();
        [$start, $end] = $this->outletHistoryDateRange($request);
        $hasSearch = trim((string) $search) !== '';

        $transactions = $this->enrichedSummaryQuery(
            $request,
            [$start, $end],
            true,
            true,
            false,
            count($months) > 1 ? $months : null,
            $request->get('coverage_id')
        );

        if ($hasSearch) {
            $this->applyHistorySearch($transactions, (string) $search);
        }

        $query = $this->wrapAsSellout($transactions);

        return $query
            ->selectRaw("
                so.dist,
                so.id_outlet,
                so.id_cbg,
                so.id_konversi,
                so.outlet_name,
                so.coverage_id,
                so.coverage_desc,
                so.coverage_nik,
                so.user_name,
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
                'so.id_cbg',
                'so.id_konversi',
                'so.outlet_name',
                'so.coverage_id',
                'so.coverage_desc',
                'so.coverage_nik',
                'so.user_name',
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

    public function annualCacheKey(string $prefix, DashboardFilterRequest $request, ?string $extra = null): string
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
                COALESCE(NULLIF(so.id_konversi, ""), master.id_konversi, so.id_outlet) as id_konversi,
                COALESCE(NULLIF(MAX(master.nama_outlet), ""), NULLIF(MAX(so.nama_outlet), ""), so.id_outlet) as outlet_name,
                COALESCE(NULLIF(so.code_product, ""), NULLIF(so.code_prod_ph, ""), "-") as sku,
                COALESCE(NULLIF(so.`desc`, ""), so.code_product, so.code_prod_ph, "-") as product_name,
                SUM(so.netto) as netto
            ')
            ->groupBy('so.id_outlet', 'so.id_konversi', 'sku', 'product_name')
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

    public function formatNNName(?string $covDesc, ?string $level = 'MR'): string
    {
        if (!$covDesc) {
            return 'NN';
        }
        $covDesc = trim((string) $covDesc);
        if (preg_match('/^NN/i', $covDesc)) {
            return $covDesc;
        }

        if (preg_match('/^([A-Za-z]+)[-_]([A-Za-z]+)(\d*)$/', $covDesc, $matches)) {
            $pilar = strtoupper($matches[1]);
            $cbg = strtoupper($matches[2]);
            $num = isset($matches[3]) ? $matches[3] : '';
            $lvl = $level ? $level : 'MR';
            return 'NN' . $num . ' ' . $lvl . ' ' . $pilar . ' ' . $cbg;
        }

        return 'NN - ' . $covDesc;
    }

    public function achievementByRegional(DashboardFilterRequest $request): \Illuminate\Support\Collection
    {
        $regionConfigs = [
            'R1' => ['keys' => ['1', 'R1'], 'coverage_id' => '2.1', 'label' => 'R1', 'desc' => 'Regional 1'],
            'R2' => ['keys' => ['2', 'R2'], 'coverage_id' => '2.2', 'label' => 'R2', 'desc' => 'Regional 2'],
            'R3' => ['keys' => ['3', 'R3'], 'coverage_id' => '2.3', 'label' => 'R3', 'desc' => 'Regional 3'],
            'R4' => ['keys' => ['4', 'R4'], 'coverage_id' => '2.4', 'label' => 'R4', 'desc' => 'Regional 4'],
            'CMA BARAT' => ['keys' => ['CMA BARAT'], 'coverage_id' => '1.1', 'label' => 'CMA BARAT', 'desc' => 'CMA Barat'],
            'CMA TIMUR' => ['keys' => ['CMA TIMUR'], 'coverage_id' => '1.2', 'label' => 'CMA TIMUR', 'desc' => 'CMA Timur'],
            'OTC BARAT' => ['keys' => ['OTC BARAT'], 'coverage_id' => '1.4', 'label' => 'OTC BARAT', 'desc' => 'OTC Barat'],
            'OTC TIMUR' => ['keys' => ['OTC TIMUR'], 'coverage_id' => '1.4', 'label' => 'OTC TIMUR', 'desc' => 'OTC Timur'],
            'MODERN OUTLET' => ['keys' => ['MODERN CHANNEL', 'MODERN OUTLET', 'MMR'], 'coverage_id' => '1.3', 'label' => 'MODERN OUTLET', 'desc' => 'Modern Outlet'],
        ];

        [$start, $end] = $this->normalizeDateRange([$request->startDate(), $request->endDate()]);

        $realQuery = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->whereBetween('so.tgl_faktur', [$start, $end]);

        $this->applyGlobalFilters($realQuery, $request, 'so.pilar_sub', 'so.dist', 'so.code_prod_ph');

        $realRows = $realQuery->selectRaw("UPPER(TRIM(so.regional)) as regional, SUM(so.netto) as total_netto")
            ->whereNotNull('so.regional')
            ->where('so.regional', '!=', '')
            ->groupBy(DB::raw("UPPER(TRIM(so.regional))"))
            ->pluck('total_netto', 'regional');

        $topRows = $this->topMarketingBaseQuery($request)
            ->selectRaw("
                UPPER(TRIM(top.region)) as region,
                TRIM(top.rm) as rm,
                SUM(top.val_net) as total_top
            ")
            ->groupBy(DB::raw("UPPER(TRIM(top.region))"), DB::raw("TRIM(top.rm)"))
            ->get();

        $covIds = collect($regionConfigs)->pluck('coverage_id')->unique()->values()->all();
        $mappings = DB::connection(self::CONN_DWH)
            ->table('crm_mst_mappings')
            ->whereIn('id_coverage', $covIds)
            ->whereNull('deleted_at')
            ->orderByDesc('is_active')
            ->get();

        $niks = $mappings->pluck('cov_nik')->filter()->unique()->values()->all();
        $users = !empty($niks) ? User::whereIn('nik', $niks)->pluck('name', 'nik')->all() : [];

        $hasChildrenSet = DB::connection(self::CONN_DWH)
            ->table('crm_mst_mappings')
            ->where('cov_level', 3)
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($m) {
                $parts = explode('.', (string) $m->id_coverage);
                return count($parts) >= 2 ? $parts[0] . '.' . $parts[1] : null;
            })
            ->filter()
            ->unique()
            ->flip()
            ->all();

        $results = [];
        foreach ($regionConfigs as $code => $cfg) {
            $realVal = 0.0;
            foreach ($cfg['keys'] as $key) {
                if (isset($realRows[$key])) {
                    $realVal += (float) $realRows[$key];
                }
            }

            $topVal = 0.0;
            foreach ($topRows as $tr) {
                $rKey = (string) $tr->region;
                $rmKey = (string) $tr->rm;
                if (in_array($rKey, $cfg['keys'], true) || $rmKey === $cfg['coverage_id']) {
                    $topVal += (float) $tr->total_top;
                }
            }

            $mapping = $mappings->firstWhere('id_coverage', $cfg['coverage_id']);
            $nik = $mapping ? $mapping->cov_nik : null;
            $name = ($nik && isset($users[$nik])) ? $users[$nik] : ($mapping ? $mapping->cov_desc : $cfg['desc']);

            $ach = $topVal > 0 ? round(($realVal / $topVal) * 100, 2) : 0.0;
            $hasChildren = isset($hasChildrenSet[$cfg['coverage_id']]);

            $results[] = (object) [
                'id'           => $code,
                'code'         => $code,
                'label'        => $cfg['label'],
                'coverage_id'  => $cfg['coverage_id'],
                'nik'          => $nik,
                'name'         => $name ? $name : $cfg['label'],
                'rm_name'      => $name ? $name : $cfg['label'],
                'divisi'       => $mapping ? ($mapping->cov_divisi ? $mapping->cov_divisi : '-') : '-',
                'real'         => round($realVal),
                'top'          => round($topVal),
                'achievement'  => $ach,
                'has_children' => $hasChildren,
                'level'        => 'RM',
            ];
        }

        return collect($results);
    }

    public function achievementByPm(DashboardFilterRequest $request): \Illuminate\Support\Collection
    {
        $pmMappings = DB::connection(self::CONN_DWH)
            ->table('crm_mst_mappings')
            ->where('cov_level_desc', 'PM')
            ->whereNull('deleted_at')
            ->orderBy('id_coverage')
            ->get();

        $niks = $pmMappings->pluck('cov_nik')->filter()->unique()->values()->all();
        $users = !empty($niks) ? User::whereIn('nik', $niks)->pluck('name', 'nik')->all() : [];

        $topQuery = $this->topMarketingBaseQuery($request);
        $topRows = $topQuery->selectRaw("TRIM(top.pm) as pm, SUM(top.val_net) as total_top")
            ->whereNotNull('top.pm')
            ->where('top.pm', '!=', '')
            ->groupBy(DB::raw("TRIM(top.pm)"))
            ->pluck('total_top', 'pm');

        [$start, $end] = $this->normalizeDateRange([$request->startDate(), $request->endDate()]);
        $realQuery = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->join('crm_mst_products as mp', 'so.code_prod_ph', '=', 'mp.pt_ph')
            ->whereNull('mp.deleted_at')
            ->whereBetween('so.tgl_faktur', [$start, $end]);

        $this->applyGlobalFilters($realQuery, $request, 'so.pilar_sub', 'so.dist', 'so.code_prod_ph');

        $realRows = $realQuery->selectRaw("mp.id_coverage as pm, SUM(so.netto) as total_netto")
            ->groupBy('mp.id_coverage')
            ->pluck('total_netto', 'pm');

        $results = [];
        foreach ($pmMappings as $pm) {
            $covId = (string) $pm->id_coverage;
            $nik = $pm->cov_nik;
            $name = ($nik && isset($users[$nik])) ? $users[$nik] : ($pm->cov_desc ? $pm->cov_desc : 'PM ' . $covId);
            $realVal = isset($realRows[$covId]) ? (float) $realRows[$covId] : 0.0;
            $topVal = isset($topRows[$covId]) ? (float) $topRows[$covId] : 0.0;
            $ach = $topVal > 0 ? round(($realVal / $topVal) * 100, 2) : 0.0;

            $results[] = (object) [
                'id'           => $covId,
                'code'         => 'PM ' . $covId,
                'label'        => $name,
                'coverage_id'  => $covId,
                'nik'          => $nik,
                'name'         => $name,
                'pm_name'      => $name,
                'divisi'       => $pm->cov_divisi ? $pm->cov_divisi : 'Marketing',
                'real'         => round($realVal),
                'top'          => round($topVal),
                'achievement'  => $ach,
                'has_children' => true,
                'level'        => 'PM',
            ];
        }

        return collect($results);
    }

    public function achievementSubordinates(DashboardFilterRequest $request, string $parentCoverageId, string $targetLevel): \Illuminate\Support\Collection
    {
        $targetLevel = strtoupper(trim($targetLevel));
        $targetNum = $this->levelToNum($targetLevel);

        $mappingsQuery = DB::connection(self::CONN_DWH)
            ->table('crm_mst_mappings')
            ->where('cov_level', $targetNum)
            ->whereNull('deleted_at');

        if ($targetLevel === 'AM') {
            $amInTop = DB::connection(self::CONN_DWH)
                ->table('crm_top_marketing')
                ->where('sam', $parentCoverageId)
                ->pluck('am')
                ->unique()
                ->values()
                ->all();

            $mappingsQuery->where(function ($q) use ($parentCoverageId, $amInTop) {
                $q->where('id_coverage', 'like', $parentCoverageId . '.%');
                if (!empty($amInTop)) {
                    $q->orWhereIn('id_coverage', $amInTop);
                }
            });
        } elseif ($targetLevel === 'MR') {
            $mappingsQuery->where('id_coverage', 'like', $parentCoverageId . '.%');
        } else {
            $mappingsQuery->where('id_coverage', 'like', $parentCoverageId . '.%');
        }

        $mappings = $mappingsQuery->orderBy('id_coverage')->get();
        if ($mappings->isEmpty()) {
            return collect();
        }

        $niks = $mappings->pluck('cov_nik')->filter()->unique()->values()->all();
        $users = !empty($niks) ? User::whereIn('nik', $niks)->pluck('name', 'nik')->all() : [];

        $nextLevelNum = (int) $targetNum + 1;
        $childCounts = [];
        if ($nextLevelNum <= 5) {
            $childCounts = DB::connection(self::CONN_DWH)
                ->table('crm_mst_mappings')
                ->where('cov_level', $nextLevelNum)
                ->whereNull('deleted_at')
                ->pluck('id_coverage')
                ->all();
        }

        $levelCol = strtolower($targetLevel);
        $topRows = $this->topMarketingBaseQuery($request)
            ->whereNotNull('top.' . $levelCol)
            ->selectRaw("TRIM(top.{$levelCol}) as cov_id, SUM(top.val_net) as total_top")
            ->groupBy(DB::raw("TRIM(top.{$levelCol})"))
            ->pluck('total_top', 'cov_id');

        [$start, $end] = $this->normalizeDateRange([$request->startDate(), $request->endDate()]);
        $realRows = collect();

        if ($targetLevel === 'MR') {
            $realQuery = DB::connection(self::CONN_DWH)
                ->table(self::SUMMARY_TABLE . ' as so')
                ->join('crm_mst_cust_cov as mo', function ($j) {
                    $j->on('so.id_konversi', '=', 'mo.id_konversi')
                        ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mo.pilar'));
                })
                ->where('mo.id_coverage', 'like', $parentCoverageId . '.%')
                ->whereBetween('so.tgl_faktur', [$start, $end]);

            $this->applyGlobalFilters($realQuery, $request, 'so.pilar_sub', 'so.dist', 'so.code_prod_ph');

            $realRows = $realQuery->selectRaw("mo.id_coverage as cov_id, SUM(so.netto) as total_netto")
                ->groupBy('mo.id_coverage')
                ->pluck('total_netto', 'cov_id');
        } elseif ($targetLevel === 'AM') {
            $realQuery = DB::connection(self::CONN_DWH)
                ->table(self::SUMMARY_TABLE . ' as so')
                ->join('crm_mst_cust_cov as mo', function ($j) {
                    $j->on('so.id_konversi', '=', 'mo.id_konversi')
                        ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mo.pilar'));
                })
                ->whereBetween('so.tgl_faktur', [$start, $end]);

            $this->applyGlobalFilters($realQuery, $request, 'so.pilar_sub', 'so.dist', 'so.code_prod_ph');

            $realRows = $realQuery->selectRaw("
                SUBSTRING_INDEX(mo.id_coverage, '.', 4) as cov_id,
                SUM(so.netto) as total_netto
            ")
            ->groupBy(DB::raw("SUBSTRING_INDEX(mo.id_coverage, '.', 4)"))
            ->pluck('total_netto', 'cov_id');
        } else {
            $realQuery = DB::connection(self::CONN_DWH)
                ->table(self::SUMMARY_TABLE . ' as so')
                ->join('crm_mst_cust_cov as mo', function ($j) {
                    $j->on('so.id_konversi', '=', 'mo.id_konversi')
                        ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mo.pilar'));
                })
                ->where('mo.id_coverage', 'like', $parentCoverageId . '.%')
                ->whereBetween('so.tgl_faktur', [$start, $end]);

            $this->applyGlobalFilters($realQuery, $request, 'so.pilar_sub', 'so.dist', 'so.code_prod_ph');

            $realRows = $realQuery->selectRaw("
                SUBSTRING_INDEX(mo.id_coverage, '.', 3) as cov_id,
                SUM(so.netto) as total_netto
            ")
            ->groupBy(DB::raw("SUBSTRING_INDEX(mo.id_coverage, '.', 3)"))
            ->pluck('total_netto', 'cov_id');
        }

        $results = [];
        foreach ($mappings as $m) {
            $covId = (string) $m->id_coverage;
            $nik = $m->cov_nik;
            $userName = ($nik && isset($users[$nik])) ? $users[$nik] : null;

            if (!$userName) {
                $displayName = $this->formatNNName($m->cov_desc, $targetLevel);
            } else {
                $displayName = $userName;
            }

            $realVal = isset($realRows[$covId]) ? (float) $realRows[$covId] : 0.0;
            $topVal = isset($topRows[$covId]) ? (float) $topRows[$covId] : 0.0;
            $ach = $topVal > 0 ? round(($realVal / $topVal) * 100, 2) : 0.0;

            $hasChildren = false;
            if ($nextLevelNum <= 5) {
                foreach ($childCounts as $cc) {
                    if (strpos((string) $cc, $covId . '.') === 0) {
                        $hasChildren = true;
                        break;
                    }
                }
            }

            $results[] = (object) [
                'id'           => $covId,
                'coverage_id'  => $covId,
                'nik'          => $nik,
                'name'         => $displayName,
                'label'        => $displayName,
                'level'        => $targetLevel,
                'divisi'       => $m->cov_divisi ? $m->cov_divisi : '-',
                'pilar'        => $m->cov_pilar ? $m->cov_pilar : '-',
                'cov_desc'     => $m->cov_desc,
                'real'         => round($realVal),
                'top'          => round($topVal),
                'achievement'  => $ach,
                'has_children' => $hasChildren,
            ];
        }

        return collect($results);
    }

    public function productsByPm(DashboardFilterRequest $request, string $pmCoverageId): \Illuminate\Support\Collection
    {
        $prods = DB::connection(self::CONN_DWH)
            ->table('crm_mst_products')
            ->where('id_coverage', $pmCoverageId)
            ->whereNull('deleted_at')
            ->pluck('pt_ph')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($prods)) {
            return collect();
        }

        $topProd = $this->topMarketingBaseQuery($request)
            ->where('top.pm', $pmCoverageId)
            ->whereIn('top.kode_ph', $prods)
            ->selectRaw('top.kode_ph, MAX(top.nama_prod) as nama, SUM(top.val_net) as t')
            ->groupBy('top.kode_ph')
            ->get()
            ->keyBy('kode_ph');

        [$start, $end] = $this->normalizeDateRange([$request->startDate(), $request->endDate()]);
        $realProd = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->whereIn('so.code_prod_ph', $prods)
            ->whereBetween('so.tgl_faktur', [$start, $end]);

        $this->applyGlobalFilters($realProd, $request, 'so.pilar_sub', 'so.dist', 'so.code_prod_ph');

        $realRows = $realProd->selectRaw("so.code_prod_ph as kode_ph, MAX(COALESCE(so.`desc`, '')) as nama, SUM(so.netto) as r")
            ->groupBy('so.code_prod_ph')
            ->get()
            ->keyBy('kode_ph');

        $results = [];
        foreach ($prods as $p) {
            $top = isset($topProd[$p]) ? (float) $topProd[$p]->t : 0.0;
            $real = isset($realRows[$p]) ? (float) $realRows[$p]->r : 0.0;
            $name = (isset($topProd[$p]) && !empty($topProd[$p]->nama))
                ? $topProd[$p]->nama
                : (isset($realRows[$p]) && !empty($realRows[$p]->nama) ? $realRows[$p]->nama : $p);

            $ach = $top > 0 ? round(($real / $top) * 100, 2) : 0.0;

            $results[] = (object) [
                'id'           => $p,
                'code'         => $p,
                'name'         => $name,
                'label'        => '[' . $p . '] ' . $name,
                'product_name' => $name,
                'top'          => round($top),
                'real'         => round($real),
                'achievement'  => $ach,
                'has_children' => false,
                'level'        => 'PRODUCT',
            ];
        }

        return collect($results)->sortByDesc('achievement')->values();
    }

    public function searchAchievement(DashboardFilterRequest $request, string $search, string $mode = 'rm'): \Illuminate\Support\Collection
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return collect();
        }

        if ($mode === 'pm') {
            $pms = $this->achievementByPm($request);
            $results = [];
            foreach ($pms as $pm) {
                $prods = $this->productsByPm($request, $pm->coverage_id);
                $matchingProds = $prods->filter(function ($prod) use ($needle) {
                    return strpos(mb_strtolower((string) $prod->name), $needle) !== false
                        || strpos(mb_strtolower((string) $prod->code), $needle) !== false;
                });

                if (strpos(mb_strtolower((string) $pm->name), $needle) !== false
                    || strpos(mb_strtolower((string) $pm->code), $needle) !== false
                    || $matchingProds->isNotEmpty()) {
                    $results[] = (object) [
                        'pm_name'      => $pm->name,
                        'product_name' => $matchingProds->isNotEmpty() ? $matchingProds->first()->name : '-',
                        'code'         => $pm->code,
                        'real'         => $matchingProds->isNotEmpty() ? $matchingProds->sum('real') : $pm->real,
                        'top'          => $matchingProds->isNotEmpty() ? $matchingProds->sum('top') : $pm->top,
                        'achievement'  => $matchingProds->isNotEmpty() ? ($matchingProds->sum('top') > 0 ? round(($matchingProds->sum('real') / $matchingProds->sum('top')) * 100, 2) : 0) : $pm->achievement,
                    ];
                }
            }
            return collect($results);
        }

        $matchingUsers = User::where('name', 'like', "%{$needle}%")
            ->orWhere('nik', 'like', "%{$needle}%")
            ->get();
        $matchingNiks = $matchingUsers->pluck('nik')->all();

        $matchingMappings = DB::connection(self::CONN_DWH)
            ->table('crm_mst_mappings')
            ->where(function ($q) use ($matchingNiks, $needle) {
                if (!empty($matchingNiks)) {
                    $q->whereIn('cov_nik', $matchingNiks);
                }
                $q->orWhere('cov_desc', 'like', "%{$needle}%")
                  ->orWhere('id_coverage', 'like', "%{$needle}%");
            })
            ->whereNull('deleted_at')
            ->get();

        if ($matchingMappings->isEmpty()) {
            return collect();
        }

        $allCovs = DB::connection(self::CONN_DWH)
            ->table('crm_mst_mappings')
            ->whereNull('deleted_at')
            ->get();

        $allNiks = $allCovs->pluck('cov_nik')->filter()->unique()->values()->all();
        $userNames = !empty($allNiks) ? User::whereIn('nik', $allNiks)->pluck('name', 'nik')->all() : [];

        $covMap = [];
        foreach ($allCovs as $ac) {
            $covMap[$ac->id_coverage] = $ac;
        }

        $self = $this;
        $resolveName = function ($id, $lvl) use ($covMap, $userNames, $self) {
            if (!$id || !isset($covMap[$id])) return '-';
            $nik = $covMap[$id]->cov_nik;
            if ($nik && isset($userNames[$nik])) return $userNames[$nik];
            return $self->formatNNName($covMap[$id]->cov_desc, $lvl);
        };

        $results = [];
        foreach ($matchingMappings as $mm) {
            $parts = explode('.', (string) $mm->id_coverage);
            $rmId = count($parts) >= 2 ? $parts[0] . '.' . $parts[1] : null;
            $samId = count($parts) >= 3 ? $parts[0] . '.' . $parts[1] . '.' . $parts[2] : null;
            $amId = count($parts) >= 4 ? $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.' . $parts[3] : null;
            $mrId = count($parts) >= 5 ? $mm->id_coverage : null;

            $levelCol = strtolower($mm->cov_level_desc ? $mm->cov_level_desc : $this->numToLevel((string) $mm->cov_level));
            if (!in_array($levelCol, ['rm', 'sam', 'am', 'mr'], true)) {
                $levelCol = 'mr';
            }
            $top = (float) $this->topMarketingBaseQuery($request)
                ->where('top.' . $levelCol, $mm->id_coverage)
                ->sum('top.val_net');

            [$start, $end] = $this->normalizeDateRange([$request->startDate(), $request->endDate()]);
            $real = (float) DB::connection(self::CONN_DWH)
                ->table(self::SUMMARY_TABLE . ' as so')
                ->join('crm_mst_cust_cov as mo', function ($j) {
                    $j->on('so.id_konversi', '=', 'mo.id_konversi')
                        ->whereRaw($this->pilarMatchSql('so.pilar_sub', 'mo.pilar'));
                })
                ->where('mo.id_coverage', 'like', $mm->id_coverage . '%')
                ->whereBetween('so.tgl_faktur', [$start, $end])
                ->sum('so.netto');

            $ach = $top > 0 ? round(($real / $top) * 100, 2) : 0.0;

            $results[] = (object) [
                'rm'          => $resolveName($rmId, 'RM'),
                'sam'         => $resolveName($samId, 'SAM'),
                'am'          => $resolveName($amId, 'AM'),
                'mr'          => $resolveName($mrId, 'MR'),
                'nik'         => $mm->cov_nik,
                'coverage_id' => $mm->id_coverage,
                'real'        => round($real),
                'top'         => round($top),
                'achievement' => $ach,
            ];
        }

        return collect($results);
    }
}
