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


    public function promoPilarSql(string $column): string
    {
        $value = "CAST(TRIM({$column}) AS UNSIGNED)";

        return "CASE
            WHEN {$value} IN (101, 102, 103, 104, 105) THEN 'OTC'
            WHEN {$value} IN (206, 207, 208, 209) THEN 'ETHIPROM'
            WHEN {$value} IN (311, 312, 313, 314, 315, 316, 317, 318, 319) THEN 'OGB'
            WHEN {$value} IN (417, 418, 419, 420) THEN 'ETHICOM'
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

        return strtoupper(trim($pilar));
    }

    public function productCoverageTable(string $alias = 'mp'): string
    {
        return "(
            SELECT
                pt_ph COLLATE utf8mb4_0900_ai_ci as pt_ph,
                id_coverage COLLATE utf8mb4_0900_ai_ci as id_coverage
            FROM {$this->dwhTable('crm_mst_products')}
            WHERE deleted_at IS NULL
            GROUP BY
                pt_ph COLLATE utf8mb4_0900_ai_ci,
                id_coverage COLLATE utf8mb4_0900_ai_ci
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
                'tr_desc1',
                'tr_desc2',
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
                tr_desc1    VARCHAR(255)   COLLATE utf8mb4_0900_ai_ci,
                tr_desc2    VARCHAR(255)   COLLATE utf8mb4_0900_ai_ci,
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
            $request->startDate(),
            $request->endDate(),
            json_encode($request->get('pilar', [])),
            json_encode($request->get('product', [])),
            json_encode($request->get('pm', [])),
            $extra ?? '',
        ]));
    }

    // -------------------------------------------------------
    // BASE QUERY
    //
    // Query transaksi langsung ke EIS untuk endpoint yang tidak butuh join.
    // Nama produk tersedia di ph_tr_hist via tr_desc1 + tr_desc2.
    // -------------------------------------------------------

    public function baseTransactionQuery(
        SellingInFilterRequest $request,
        ?array $dateRange = null
    ): Builder {
        $range = $dateRange ?? [$request->startDate(), $request->endDate()];
        [$start, $end] = $this->normalizeDateRange($range);

        $source = DB::connection(self::CONN_EIS)
            ->table('ph_tr_hist')
            ->whereBetween('tr_eff_date', [$start, $end])
            ->select([
                'tr_eff_date',
                'tr_part',
                'tr_desc1',
                'tr_desc2',
                'tr_promo',
                'tr_price',
                'tr_qty',
                DB::raw('(tr_price * (tr_qty * -1)) as netto'),
            ]);

        $query = DB::connection(self::CONN_EIS)
            ->query()
            ->fromSub($source, 'si');

        if ($request->filled('pilar')) {
            $query->whereIn(
                DB::raw($this->promoPilarSql('si.tr_promo')),
                collect($request->input('pilar'))
                    ->map(fn($pilar) => $this->normalizePilarValue($pilar))
                    ->unique()
                    ->values()
                    ->all()
            );
        }

        if ($request->filled('product')) {
            $query->whereIn('si.tr_part', $request->input('product'));
        }

        // Filter PM: ambil list tr_part yg dicoverage PM tersebut di DWH
        if ($request->filled('pm')) {
            $parts = $this->getPartsByPm($request->input('pm'));
            $query->whereIn('si.tr_part', $parts ?: ['__no_match__']);
        }

        return $query;
    }


    // -------------------------------------------------------
    // TEMP BASE QUERY
    //
    // Dipakai sementara untuk endpoint yang masih perlu join lintas koneksi,
    // seperti Top PM ke mapping coverage dan users.
    // -------------------------------------------------------

    public function baseQuery(
        SellingInFilterRequest $request,
        ?array $dateRange = null
    ): Builder {
        $range = $dateRange ?? [$request->startDate(), $request->endDate()];
        $this->ensureTempTable($range);

        $query = DB::connection(self::CONN_AUTH)
            ->table('tmp_selling_in as si');

        // Filter pilar
        if ($request->filled('pilar')) {
            $query->whereIn(
                DB::raw($this->promoPilarSql('si.tr_promo')),
                collect($request->input('pilar'))
                    ->map(fn($pilar) => $this->normalizePilarValue($pilar))
                    ->unique()
                    ->values()
                    ->all()
            );
        }

        if ($request->filled('product')) {
            $query->whereIn('si.tr_part', $request->input('product'));
        }

        // Filter PM: ambil list tr_part yg dicoverage PM tersebut di DWH
        if ($request->filled('pm')) {
            $parts = $this->getPartsByPm($request->input('pm'));
            $query->whereIn('si.tr_part', $parts ?: ['__no_match__']);
        }

        return $query;
    }

    // -------------------------------------------------------
    // PM LOOKUP
    // -------------------------------------------------------

    /**
     * Kembalikan list PM (nik + name) untuk select2 autocomplete.
     */
    public function pmLookup(string $search = '', int $limit = 30): array
    {
        return DB::connection(self::CONN_AUTH)
            ->table(DB::raw($this->productCoverageTable('mp')))
            ->join(
                DB::raw($this->dwhTable('crm_mst_mappings') . ' as mc'),
                fn ($join) => $join
                    ->on('mp.id_coverage', '=', DB::raw('mc.id_coverage COLLATE utf8mb4_0900_ai_ci'))
                    ->whereNull('mc.deleted_at')
                    ->where('mc.is_active', true)
            )
            ->join(
                'users as u',
                DB::raw('mc.cov_nik COLLATE utf8mb4_0900_ai_ci'),
                '=',
                DB::raw('u.nik COLLATE utf8mb4_0900_ai_ci')
            )
            ->when($search, fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('u.name', 'like', "%{$search}%")
                   ->orWhere('u.nik', 'like', "%{$search}%");
            }))
            ->selectRaw('u.nik, u.name')
            ->groupBy('u.nik', 'u.name')
            ->orderBy('u.name')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['id' => $r->nik, 'text' => "{$r->name} ({$r->nik})"])
            ->toArray();
    }

    /**
     * Dapatkan daftar tr_part (kode produk) yang di-coverage oleh PM tertentu.
     */
    private function getPartsByPm(array $niks): array
    {
        return DB::connection(self::CONN_AUTH)
            ->table(DB::raw($this->productCoverageTable('mp')))
            ->join(
                DB::raw($this->dwhTable('crm_mst_mappings') . ' as mc'),
                fn ($j) => $j
                    ->on('mp.id_coverage', '=', DB::raw('mc.id_coverage COLLATE utf8mb4_0900_ai_ci'))
                    ->whereNull('mc.deleted_at')
                    ->where('mc.is_active', true)
            )
            ->whereIn(DB::raw('mc.cov_nik COLLATE utf8mb4_0900_ai_ci'), $niks)
            ->pluck('mp.pt_ph')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Count helper untuk pagination DataTables.
     */
    public function countGrouped(Builder $query): int
    {
        return $query->getConnection()->query()->fromSub($query, 'agg')->count();
    }
}
