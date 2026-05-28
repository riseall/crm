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
    private const CACHE_VERSION = 'v10';
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

    private function productMasterQuery(): Builder
    {
        return DB::connection(self::CONN_DWH)
            ->table('v_pt_mstr_dist')
            ->selectRaw('pt_dist, dist, MIN(pilar) as pilar')
            ->groupBy('pt_dist', 'dist');
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
            WHEN UPPER(TRIM({$column})) IN ('ETHICAL', 'ETHICOM', 'ETHIPROM') THEN 'ETHICAL'
            WHEN UPPER(TRIM({$column})) IN ('OGB', 'GENERIK') THEN 'OGB'
            WHEN UPPER(TRIM({$column})) = 'OTC' THEN 'OTC'
            ELSE UPPER(TRIM({$column}))
        END";
    }

    private function normalizePilarValue(?string $pilar): ?string
    {
        if ($pilar === null || $pilar === '') {
            return null;
        }

        $pilar = strtoupper(trim($pilar));

        switch ($pilar) {
            case 'ETHICOM':
            case 'ETHIPROM':
                return 'ETHICAL';
            case 'GENERIK':
                return 'OGB';
            default:
                return $pilar;
        }
    }

    // -------------------------------------------------------
    // STANDARD JOINS - Query langsung dari summary table
    // -------------------------------------------------------
    private function applyStandardJoins($query, bool $isHierarchical = false)
    {
        return $query
            ->leftJoinSub($this->productMasterQuery(), 'vp', function ($join) {
                $join->on('so.code_product', '=', 'vp.pt_dist')
                    ->on('so.dist', '=', 'vp.dist');
            })
            ->leftJoin(DB::raw($this->dwhTable('mst_cust_dist') . ' as vcd'), function ($join) {
                $join->on('so.id_outlet', '=', 'vcd.id_outlet')
                    ->on('so.id_cbg', '=', 'vcd.id_cbg_dist');
            })
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as mo'), function ($join) use ($isHierarchical) {
                $join->on('so.id_outlet', '=', 'mo.id_outlet')
                    ->whereNull('mo.deleted_at');

                if (!$isHierarchical) {
                    $join->whereExists(function ($exists) {
                        $exists->select(DB::raw(1))
                            ->from(DB::raw($this->dwhTable('crm_mst_mappings') . ' as mx'))
                            ->whereColumn('mx.id_coverage', 'mo.id_coverage')
                            ->whereNull('mx.deleted_at')
                            ->whereRaw($this->normalizedPilarSql('vp.pilar') . ' = ' . $this->normalizedPilarSql('mx.cov_pilar'));
                    });
                }
            });
    }

    /**
     * Join minimal untuk chart - tanpa users join
     */
    private function applyChartJoins($query, bool $isHierarchical = false)
    {
        return $query
            ->leftJoinSub($this->productMasterQuery(), 'vp', function ($join) {
                $join->on('so.code_product', '=', 'vp.pt_dist')
                    ->on('so.dist', '=', 'vp.dist');
            })
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as mo'), function ($join) use ($isHierarchical) {
                $join->on('so.id_outlet', '=', 'mo.id_outlet')
                    ->whereNull('mo.deleted_at');

                if (!$isHierarchical) {
                    $join->whereExists(function ($exists) {
                        $exists->select(DB::raw(1))
                            ->from(DB::raw($this->dwhTable('crm_mst_mappings') . ' as mx'))
                            ->whereColumn('mx.id_coverage', 'mo.id_coverage')
                            ->whereNull('mx.deleted_at')
                            ->whereRaw($this->normalizedPilarSql('vp.pilar') . ' = ' . $this->normalizedPilarSql('mx.cov_pilar'));
                    });
                }
            });
    }

    private function normalizeDateRange(array $dateRange): array
    {
        return [
            Carbon::parse($dateRange[0])->startOfDay()->toDateTimeString(),
            Carbon::parse($dateRange[1])->endOfDay()->toDateTimeString(),
        ];
    }

    /**
     * Refresh summary untuk tanggal range tertentu
     * Query langsung dari source table (KFTD, AAM, METD) di DWH
     */
    public function refreshSummaryRange(string $from, string $to): void
    {
        [$start, $end] = $this->normalizeDateRange([$from, $to]);

        $this->insertKftdSummary($start, $end);
        $this->insertAamSummary($start, $end);
        $this->insertMetdSummary($start, $end);
    }

    /**
     * Insert atau update data KFTD ke summary table di DWH
     */
    private function insertKftdSummary(string $start, string $end): void
    {
        DB::connection(self::CONN_DWH)->statement(
            "INSERT INTO " . self::SUMMARY_TABLE . " (
                source_table,
                source_id,
                tgl_faktur,
                dist,
                id_outlet,
                id_cbg,
                code_product,
                netto,
                created_at,
                updated_at
            )
            SELECT
                'KFTD' as source_table,
                k.id_selling_out,
                k.posting_date,
                'KFTD' as dist,
                k.ship_to_party,
                k.sales_office,
                k.material,
                ((k.total_penjualan + k.total_discount) - (k.dis_zd02_amount + k.dis_zd04_amount)) as netto,
                NOW(),
                NOW()
            FROM selling_out_kftd AS k
            WHERE k.principle = '7000000344'
                AND k.posting_date BETWEEN ? AND ?
            ON DUPLICATE KEY UPDATE 
                tgl_faktur = VALUES(tgl_faktur),
                netto = VALUES(netto),
                updated_at = NOW()",
            [$start, $end]
        );
    }

    /**
     * Insert atau update data AAM ke summary table di DWH
     */
    private function insertAamSummary(string $start, string $end): void
    {
        DB::connection(self::CONN_DWH)->statement(
            "INSERT INTO " . self::SUMMARY_TABLE . " (
                source_table,
                source_id,
                tgl_faktur,
                dist,
                id_outlet,
                id_cbg,
                code_product,
                netto,
                created_at,
                updated_at
            )
            SELECT
                'AAM' as source_table,
                a.id,
                a.posting_date,
                'AAM' as dist,
                a.ship_to_party,
                a.sales_office,
                a.material,
                a.nilai_netto,
                NOW(),
                NOW()
            FROM selling_out_aam AS a
            WHERE a.posting_date BETWEEN ? AND ?
            ON DUPLICATE KEY UPDATE 
                tgl_faktur = VALUES(tgl_faktur),
                netto = VALUES(netto),
                updated_at = NOW()",
            [$start, $end]
        );
    }

    /**
     * Insert atau update data METD ke summary table di DWH
     */
    private function insertMetdSummary(string $start, string $end): void
    {
        DB::connection(self::CONN_DWH)->statement(
            "INSERT INTO " . self::SUMMARY_TABLE . " (
                source_table,
                source_id,
                tgl_faktur,
                dist,
                id_outlet,
                id_cbg,
                code_product,
                netto,
                created_at,
                updated_at
            )
            SELECT
                'METD' as source_table,
                m.id,
                m.tgl_faktur,
                'METD' as dist,
                m.id_outlet,
                m.kode_cbg_ph,
                m.kode_brg_metd,
                m.netto,
                NOW(),
                NOW()
            FROM selling_out_metd AS m
            WHERE m.tgl_faktur BETWEEN ? AND ?
            ON DUPLICATE KEY UPDATE 
                tgl_faktur = VALUES(tgl_faktur),
                netto = VALUES(netto),
                updated_at = NOW()",
            [$start, $end]
        );
    }

    private function mappingJoinCallback(bool $isHierarchical, ?string $targetLevelNum = null): \Closure
    {
        return function ($join) use ($isHierarchical, $targetLevelNum) {
            if ($isHierarchical) {
                $join->on(DB::raw('mo.id_coverage'), 'like', DB::raw("CONCAT(mc.id_coverage, '%')"));
            } else {
                $join->on('mo.id_coverage', '=', 'mc.id_coverage');
            }

            if ($targetLevelNum !== null) {
                $join->where('mc.cov_level', $targetLevelNum);
            }

            $join->whereNull('mc.deleted_at');

            if (!$isHierarchical) {
                $join->whereRaw($this->normalizedPilarSql('vp.pilar') . ' = ' . $this->normalizedPilarSql('mc.cov_pilar'));
            }

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
    ): void
    {
        $query->whereExists(function ($exists) use ($alias, $levelNum, $outletAlias) {
            $exists->select(DB::raw(1))
                ->from(DB::raw($this->dwhTable('crm_mst_mappings') . " as {$alias}"))
                ->whereColumn("{$alias}.id_coverage", "{$outletAlias}.id_coverage")
                ->whereNull("{$alias}.deleted_at")
                ->whereRaw($this->normalizedPilarSql('vp.pilar') . ' = ' . $this->normalizedPilarSql("{$alias}.cov_pilar"));

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

    private function applyHistorySearch(Builder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $like = '%' . $search . '%';

        $query->where(function ($q) use ($like) {
            $q->where('so.dist', 'like', $like)
                ->orWhere('so.id_outlet', 'like', $like)
                ->orWhere('vcd.nama_outlet', 'like', $like)
                ->orWhere('mc.cov_nik', 'like', $like)
                ->orWhere('ma.name', 'like', $like)
                ->orWhere('vp.pilar', 'like', $like)
                ->orWhereRaw("(CASE mc.cov_level WHEN 1 THEN 'GM' WHEN 2 THEN 'RM' WHEN 3 THEN 'SAM' WHEN 4 THEN 'AM' WHEN 5 THEN 'MR' ELSE 'Unmapped' END) like ?", [$like]);
        });
    }

    private function applyHistoryAggregateSearch(Builder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $like = '%' . $search . '%';

        $query->where(function ($q) use ($like) {
            $q->where('so.dist', 'like', $like)
                ->orWhere('so.id_outlet', 'like', $like)
                ->orWhere('vcd.outlet_name', 'like', $like)
                ->orWhere('so.coverage_nik', 'like', $like)
                ->orWhere('ma.name', 'like', $like)
                ->orWhere('so.product_pilar', 'like', $like)
                ->orWhereRaw("(CASE so.coverage_level WHEN 1 THEN 'GM' WHEN 2 THEN 'RM' WHEN 3 THEN 'SAM' WHEN 4 THEN 'AM' WHEN 5 THEN 'MR' ELSE 'Unmapped' END) like ?", [$like]);
        });
    }

    private function enrichedSummaryQuery(
        DashboardFilterRequest $request,
        bool $isHierarchical,
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
            ? $this->applyChartJoins($query, $isHierarchical)
            : $this->applyStandardJoins($query, $isHierarchical);

        $targetLevelNum = $isHierarchical
            ? $this->levelToNum($request->get('level', 'GM'))
            : null;

        $query->leftJoin(
            DB::raw($this->dwhTable('crm_mst_mappings') . ' as mc'),
            $this->mappingJoinCallback($isHierarchical, $targetLevelNum)
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

        if ($coverageId !== null && trim($coverageId) !== '') {
            $query->where('mc.id_coverage', $coverageId);
        }

        if ($request->filled('coverage_nik')) {
            $query->where('mc.cov_nik', $request->get('coverage_nik'));
        }

        $query = $this->coverageAccess->applyScope($query, 'mo.id_coverage');

        if (!$isHierarchical && $request->filled('level')) {
            $query->where('mc.cov_level', $this->levelToNum($request->get('level')));
        }

        if ($request->filled('pilar')) {
            $query->whereRaw($this->normalizedPilarSql('vp.pilar') . ' = ?', [
                $this->normalizePilarValue($request->get('pilar')),
            ]);
        }

        if ($request->filled('distributor')) {
            $query->where('so.dist', $request->get('distributor'));
        }

        $selects = [
            'so.id',
            DB::raw('MAX(so.source_table) as source_table'),
            DB::raw('MAX(so.source_id) as source_id'),
            DB::raw('MAX(so.tgl_faktur) as tgl_faktur'),
            DB::raw('MAX(so.dist) as dist'),
            DB::raw('MAX(so.id_outlet) as id_outlet'),
            DB::raw('MAX(so.id_cbg) as id_cbg'),
            DB::raw('MAX(so.code_product) as code_product'),
            DB::raw('MAX(so.netto) as netto'),
            DB::raw('MAX(vp.pilar) as product_pilar'),
            DB::raw('MAX(mc.id_coverage) as coverage_id'),
            DB::raw('MAX(mc.cov_desc) as coverage_desc'),
            DB::raw('MAX(mc.cov_nik) as coverage_nik'),
            DB::raw('MAX(mc.cov_level) as coverage_level'),
            DB::raw('MAX(mc.cov_pilar) as coverage_pilar'),
            DB::raw('MAX(mc.cov_divisi) as coverage_divisi'),
            DB::raw('MAX(mc.valid_from) as coverage_valid_from'),
            DB::raw('MAX(mc.valid_to) as coverage_valid_to'),
        ];

        if ($includeOutletName) {
            $selects[] = DB::raw("MAX(COALESCE(vcd.nama_outlet, so.id_outlet)) as outlet_name");
        } else {
            $selects[] = DB::raw('MAX(so.id_outlet) as outlet_name');
        }

        $selects[] = $includeUser
            ? DB::raw('MAX(ma.name) as user_name')
            : DB::raw('NULL as user_name');

        return $query->select($selects)
            ->groupBy('so.id');
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
        bool $isHierarchical = false,
        ?array $dateRange = null,
        ?array $months = null,
        ?string $coverageId = null
    ): Builder {
        $range = $dateRange ?? [$request->startDate(), $request->endDate()];

        return $this->wrapAsSellout(
            $this->enrichedSummaryQuery($request, $isHierarchical, $range, true, true, false, $months, $coverageId)
        );
    }

    public function drilldownAggregateQuery(
        DashboardFilterRequest $request,
        string $levelNum,
        ?string $parentId = null
    ): Builder {
        [$start, $end] = $this->normalizeDateRange([$request->startDate(), $request->endDate()]);
        $levelDepth = max((int) $levelNum, 1);
        $coverageSql = 'COALESCE(mo.id_coverage, mo_any.id_coverage)';
        $parentCoverageSql = "NULLIF(SUBSTRING_INDEX({$coverageSql}, '.', {$levelDepth}), '')";

        $mappingSub = DB::connection(self::CONN_DWH)
            ->table('crm_mst_mappings')
            ->selectRaw("
                id_coverage,
                cov_level,
                MAX(cov_desc) as cov_desc,
                MAX(cov_pilar) as cov_pilar,
                MAX(cov_nik) as cov_nik,
                MAX(cov_divisi) as cov_divisi
            ")
            ->where('cov_level', $levelNum)
            ->whereNull('deleted_at')
            ->groupBy('id_coverage', 'cov_level');

        $outletAnySub = DB::connection(self::CONN_DWH)
            ->table('crm_mst_outlets')
            ->selectRaw('id_outlet, MAX(id_coverage) as id_coverage')
            ->whereNull('deleted_at')
            ->whereNotNull('id_coverage')
            ->where('id_coverage', '<>', '')
            ->groupBy('id_outlet');

        $query = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->leftJoinSub($this->productMasterQuery(), 'vp', function ($join) {
                $join->on('so.code_product', '=', 'vp.pt_dist')
                    ->on('so.dist', '=', 'vp.dist');
            })
            ->leftJoin(DB::raw($this->dwhTable('crm_mst_outlets') . ' as mo'), function ($join) {
                $join->on('so.id_outlet', '=', 'mo.id_outlet')
                    ->whereNull('mo.deleted_at')
                    ->whereExists(function ($exists) {
                        $exists->select(DB::raw(1))
                            ->from(DB::raw($this->dwhTable('crm_mst_mappings') . ' as mx'))
                            ->whereColumn('mx.id_coverage', 'mo.id_coverage')
                            ->whereNull('mx.deleted_at')
                            ->whereRaw($this->normalizedPilarSql('vp.pilar') . ' = ' . $this->normalizedPilarSql('mx.cov_pilar'));
                    });
            })
            ->leftJoinSub($outletAnySub, 'mo_any', function ($join) {
                $join->on('so.id_outlet', '=', 'mo_any.id_outlet');
            })
            ->leftJoinSub($mappingSub, 'mc', function ($join) use ($parentCoverageSql) {
                $join->on('mc.id_coverage', '=', DB::raw($parentCoverageSql));
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
            $query->whereRaw($this->normalizedPilarSql('vp.pilar') . ' = ?', [
                $this->normalizePilarValue($request->get('pilar')),
            ]);
        }

        if ($request->filled('distributor')) {
            $query->where('so.dist', $request->get('distributor'));
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

        return $this->baseQuery($request, false, $yearRange);
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
            $this->enrichedSummaryQuery($request, false, $range, false, false, true)
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

    /**
     * Query khusus chart bulanan.
     * Tetap menghormati mapping/access filter, tapi tidak melakukan enrichment per transaksi.
     */
    public function monthlyChartQuery(DashboardFilterRequest $request): Builder
    {
        $year = (int) $request->tahun();
        $start = Carbon::create($year, 1, 1)->startOfDay()->toDateTimeString();
        $end = Carbon::create($year, 12, 31)->endOfDay()->toDateTimeString();
        $query = DB::connection(self::CONN_DWH)
            ->table(self::SUMMARY_TABLE . ' as so')
            ->leftJoinSub($this->productMasterQuery(), 'vp', function ($join) {
                $join->on('so.code_product', '=', 'vp.pt_dist')
                    ->on('so.dist', '=', 'vp.dist');
            })
            ->whereBetween('so.tgl_faktur', [$start, $end]);

        if ($this->monthlyChartNeedsOutletFilter($request)) {
            $this->applyMonthlyOutletExists($query, $request);
        }

        if ($request->filled('pilar')) {
            $query->whereRaw($this->normalizedPilarSql('vp.pilar') . ' = ?', [
                $this->normalizePilarValue($request->get('pilar')),
            ]);
        }

        if ($request->filled('distributor')) {
            $query->where('so.dist', $request->get('distributor'));
        }

        return $query
            ->selectRaw('MONTH(so.tgl_faktur) as bulan, SUM(so.netto) as total_netto')
            ->groupByRaw('MONTH(so.tgl_faktur)');
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

            $query = $this->baseQuery($request, false, [
                Carbon::create($year, $firstMonth, 1)->startOfMonth()->toDateString(),
                Carbon::create($year, $lastMonth, 1)->endOfMonth()->toDateString(),
            ], $months, $coverageId);
        } else {
            $query = $this->baseQuery($request, false, null, null, $coverageId);
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
            false,
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
            $request->bulan(),
            $request->tahun(),
            $request->get('level', ''),
            $request->get('pilar', ''),
            $request->get('distributor', ''),
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
            $request->get('pilar', ''),
            $request->get('distributor', ''),
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
