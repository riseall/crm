<?php

namespace App\Services;

use App\Http\Requests\SellingInFilterRequest;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SellingInQueryService
 */
class SellingInQueryService
{
    // -------------------------------------------------------
    // Konstanta
    // -------------------------------------------------------

    private const CACHE_TTL = 300; // 5 menit

    private const CONN_EIS  = 'db_eis';
    private const CONN_DWH  = 'db_dwh';
    private const CONN_AUTH = 'mysql';

    private ?string $currentTempRange = null;

    public function dwhTable(string $table): string
    {
        return DB::connection(self::CONN_DWH)->getDatabaseName() . '.' . $table;
    }

    public function authTable(string $table): string
    {
        return DB::connection(self::CONN_AUTH)->getDatabaseName() . '.' . $table;
    }

    public function normalizedPilarSql(string $column): string
    {
        $value = "UPPER(TRIM({$column} COLLATE utf8mb4_0900_ai_ci))";

        return "CASE
            WHEN {$value} IS NULL OR {$value} = '' THEN 'EXPORT'
            WHEN {$value} IN ('ETHICAL', 'ETHICOM', 'ETHIPROM') THEN 'ETHICAL'
            WHEN {$value} IN ('OGB', 'GENERIK') THEN 'OGB'
            WHEN {$value} = 'OTC' THEN 'OTC'
            ELSE {$value}
        END";
    }

    public function promoPilarSql(string $column): string
    {
        $value = "CAST(TRIM({$column}) AS UNSIGNED)";

        return "CASE
            WHEN {$value} IN (101, 102, 103, 104, 105) THEN 'OTC'
            WHEN {$value} IN (206, 207, 208, 209) THEN 'ETHICAL'
            WHEN {$value} IN (311, 312, 313, 314, 315, 316, 317, 318, 319) THEN 'GENERIK'
            WHEN {$value} IN (417, 418, 419, 420) THEN 'ETHICAL'
            WHEN {$value} = 510 THEN 'ALKES'
            WHEN {$value} IN (610, 611, 612, 613, 614) THEN 'TOLL IN'
            WHEN {$value} = 700 THEN 'EXPORT'
            ELSE CONCAT('LAINNYA ', {$value})
        END";
    }

    private function normalizePilarValue(?string $pilar): ?string
    {
        if ($pilar === null || trim($pilar) === '') {
            return 'EXPORT';
        }

        $pilar = strtoupper(trim($pilar));

        switch ($pilar) {
            case 'ETHICOM':
            case 'ETHIPROM':
                return 'ETHICAL';
            default:
                return $pilar;
        }
    }

    private function productMasterTable(): string
    {
        return "(
            SELECT
                pt_ph COLLATE utf8mb4_0900_ai_ci as pt_ph,
                MIN(pt_desc COLLATE utf8mb4_0900_ai_ci) as pt_desc,
                MIN(CASE
                    WHEN pilar_sub IS NULL OR TRIM(pilar_sub COLLATE utf8mb4_0900_ai_ci) = '' THEN 'EXPORT'
                    WHEN UPPER(TRIM(pilar_sub COLLATE utf8mb4_0900_ai_ci)) IN ('ETHICAL', 'ETHICOM', 'ETHIPROM') THEN 'ETHICAL'
                    WHEN UPPER(TRIM(pilar_sub COLLATE utf8mb4_0900_ai_ci)) IN ('OGB', 'GENERIK') THEN 'OGB'
                    WHEN UPPER(TRIM(pilar_sub COLLATE utf8mb4_0900_ai_ci)) = 'OTC' THEN 'OTC'
                    ELSE UPPER(TRIM(pilar_sub COLLATE utf8mb4_0900_ai_ci))
                END) as pilar,
                MIN(promotion_group COLLATE utf8mb4_0900_ai_ci) as promotion_group
            FROM {$this->dwhTable('v_pt_mstr_dist')}
            GROUP BY pt_ph COLLATE utf8mb4_0900_ai_ci
        ) as vp";
    }

    public function productCoverageTable(string $alias = 'mp'): string
    {
        return "(
            SELECT
                pt_ph COLLATE utf8mb4_0900_ai_ci as pt_ph,
                MIN(id_coverage COLLATE utf8mb4_0900_ai_ci) as id_coverage
            FROM {$this->dwhTable('crm_mst_products')}
            WHERE deleted_at IS NULL
            GROUP BY pt_ph COLLATE utf8mb4_0900_ai_ci
        ) as {$alias}";
    }

    // -------------------------------------------------------
    // TEMP TABLE
    // -------------------------------------------------------
    private function ensureTempTable(array $dateRange): void
    {
        [$start, $end] = $this->normalizeDateRange($dateRange);
        $rangeKey = "{$start}|{$end}";

        if ($this->currentTempRange === $rangeKey) {
            return;
        }

        // Ambil data dari DB EIS
        $rows = DB::connection(self::CONN_EIS)
            ->table('ph_tr_hist')
            ->whereBetween('tr_eff_date', [$start, $end])
            ->select([
                'tr_eff_date',
                'tr_part',
                'tr_promo',
                'tr_price',
                'tr_qty',
                DB::raw('(tr_price * (tr_qty * -1)) as netto'),
            ])
            ->get();

        // Buat temp table di koneksi default (CRM)
        DB::connection(self::CONN_AUTH)->statement('DROP TEMPORARY TABLE IF EXISTS tmp_selling_in');
        DB::connection(self::CONN_AUTH)->statement("
            CREATE TEMPORARY TABLE tmp_selling_in (
                tr_eff_date DATE,
                tr_part     VARCHAR(50)    COLLATE utf8mb4_0900_ai_ci,
                tr_promo    VARCHAR(20)    COLLATE utf8mb4_0900_ai_ci,
                tr_price    DECIMAL(20,4),
                tr_qty      DECIMAL(20,4),
                netto       DECIMAL(20,4)
            )
        ");

        collect($rows)
            ->chunk(500)
            ->each(function ($chunk) {
                DB::connection(self::CONN_AUTH)->table('tmp_selling_in')->insert(
                    $chunk->map(fn($row) => (array) $row)->toArray()
                );
            });

        DB::connection(self::CONN_AUTH)->statement('ALTER TABLE tmp_selling_in ADD INDEX idx_part    (tr_part)');
        DB::connection(self::CONN_AUTH)->statement('ALTER TABLE tmp_selling_in ADD INDEX idx_date    (tr_eff_date)');
        DB::connection(self::CONN_AUTH)->statement('ALTER TABLE tmp_selling_in ADD INDEX idx_promo   (tr_promo)');

        $this->currentTempRange = $rangeKey;
    }

    // -------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------

    private function normalizeDateRange(array $dateRange): array
    {
        return [
            Carbon::parse($dateRange[0])->startOfDay()->toDateTimeString(),
            Carbon::parse($dateRange[1])->endOfDay()->toDateTimeString(),
        ];
    }

    public function cacheTtl(): int
    {
        return self::CACHE_TTL;
    }

    public function cacheKey(string $prefix, SellingInFilterRequest $request, ?string $extra = null): string
    {
        return 'selling_in:' . $prefix . ':' . md5(implode('|', [
            $request->bulan(),
            $request->tahun(),
            $request->get('pilar', ''),
            $extra ?? '',
        ]));
    }

    // -------------------------------------------------------
    // BASE QUERY
    //
    // Semua query selling in dimulai dari sini.
    // tmp_selling_in di-JOIN ke v_pt_mstr_dist (DWH) untuk
    // mendapatkan: promotion_group dan pt_desc.
    // -------------------------------------------------------

    public function baseQuery(
        SellingInFilterRequest $request,
        ?array $dateRange = null
    ): Builder {
        $range = $dateRange ?? [$request->startDate(), $request->endDate()];
        $this->ensureTempTable($range);

        $query = DB::connection(self::CONN_AUTH)
            ->table('tmp_selling_in as si')
            ->leftJoin(DB::raw($this->productMasterTable()), 'si.tr_part', '=', 'vp.pt_ph');

        // Filter pilar
        if ($request->filled('pilar')) {
            $query->whereRaw($this->promoPilarSql('si.tr_promo') . ' = ?', [
                $this->normalizePilarValue($request->get('pilar')),
            ]);
        }

        return $query;
    }

    /**
     * Base query untuk range 1 tahun penuh (untuk chart YTD).
     */
    public function baseQueryByYear(SellingInFilterRequest $request): Builder
    {
        $year = $request->tahun();
        $yearRange = [
            Carbon::create($year, 1, 1)->startOfYear()->toDateString(),
            Carbon::create($year, 12, 31)->endOfYear()->toDateString(),
        ];

        return $this->baseQuery($request, $yearRange);
    }

    /**
     * Count helper untuk pagination DataTables.
     */
    public function countGrouped(Builder $query): int
    {
        return DB::query()->fromSub($query, 'agg')->count();
    }
}
