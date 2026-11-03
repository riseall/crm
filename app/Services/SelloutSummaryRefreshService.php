<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SelloutSummaryRefreshService
{
    private const CONN_DWH = 'db_dwh';
    private const SUMMARY_TABLE = 'crm_sellout_summary';

    private const COLUMNS = [
        'source_table',
        'source_id',
        'tgl_faktur',
        'no_faktur',
        'dist',
        'id_cbg',
        'id_cbg_ph',
        'id_outlet',
        'id_konversi',
        'nama_outlet',
        'segment_dist',
        'segment_mi',
        'code_product',
        'code_prod_ph',
        'desc',
        'satuan',
        'pilar_sub',
        'code_pelayanan',
        'dec_pelayanan',
        'divisi',
        'sub_divisi',
        'regional',
        'batch',
        'qty',
        'hna',
        'bruto',
        'netto',
        'created_at',
        'updated_at',
    ];

    private function normalizeDateRange(array $dateRange): array
    {
        return [
            Carbon::parse($dateRange[0])->startOfDay()->toDateTimeString(),
            Carbon::parse($dateRange[1])->endOfDay()->toDateTimeString(),
        ];
    }

    private function utf8Key(string $expression): string
    {
        return "CONVERT({$expression} USING utf8mb4) COLLATE utf8mb4_0900_ai_ci";
    }

    /**
     * Refresh summary untuk tanggal range tertentu.
     * Query langsung dari source table (KFTD, AAM, METD) di DWH.
     */
    public function refreshSummaryRange(string $from, string $to, ?callable $progress = null): void
    {
        [$start, $end] = $this->normalizeDateRange([$from, $to]);

        $rangeStart = Carbon::parse($start);
        $rangeEnd = Carbon::parse($end);
        $cursor = $rangeStart->copy()->startOfMonth();

        while ($cursor->lte($rangeEnd)) {
            $chunkStart = $cursor->copy()->max($rangeStart);
            $chunkEnd = $cursor->copy()->endOfMonth()->min($rangeEnd);

            if ($progress) {
                $progress($chunkStart->toDateString(), $chunkEnd->toDateString());
            }

            $this->insertKftdSummary($chunkStart->toDateTimeString(), $chunkEnd->toDateTimeString());
            $this->insertAamSummary($chunkStart->toDateTimeString(), $chunkEnd->toDateTimeString());
            $this->insertMetdSummary($chunkStart->toDateTimeString(), $chunkEnd->toDateTimeString());

            $cursor->addMonthNoOverflow()->startOfMonth();
        }
    }

    /**
     * Insert atau update data KFTD ke summary table di DWH.
     */
    private function insertKftdSummary(string $start, string $end): void
    {
        $this->executeSummaryInsert([
            'source' => 'KFTD',
            'from_table' => 'selling_out_kftd',
            'alias' => 'k',
            'date_col' => 'posting_date',
            'where' => "k.principle = '7000000344'",
            'filter_dist' => true,
            'select' => [
                'source_id' => 'k.id_selling_out',
                'tgl_faktur' => 'k.posting_date',
                'no_faktur' => 'k.billing_no',
                'id_cbg' => 'k.sales_office',
                'id_cbg_ph' => 'cbg.id_cbg_ph',
                'id_outlet' => 'k.ship_to_party',
                'nama_outlet' => 'k.name_ship_to',
                'segment_dist' => 'k.desc_cust_grp4',
                'code_product' => 'k.material',
                'satuan' => 'k.sales_unit',
                'code_pelayanan' => 'k.code_pelayanan',
                'dec_pelayanan' => 'k.dec_pelayanan',
                'batch' => 'k.supplier_batch',
                'qty' => 'k.quantity',
                'hna' => 'k.unit_price_penjualan',
                'bruto' => '(k.total_penjualan + k.total_discount)',
                'netto' => '((k.total_penjualan + k.total_discount) - (k.dis_zd02_amount + k.dis_zd04_amount))',
            ],
        ], $start, $end);
    }

    /**
     * Insert atau update data AAM ke summary table di DWH.
     */
    private function insertAamSummary(string $start, string $end): void
    {
        $this->executeSummaryInsert([
            'source' => 'AAM',
            'from_table' => 'selling_out_aam',
            'alias' => 'a',
            'date_col' => 'posting_date',
            'filter_dist' => true,
            'select' => [
                'source_id' => 'a.id',
                'tgl_faktur' => 'a.posting_date',
                'no_faktur' => 'a.po_number',
                'id_cbg' => 'a.sales_office',
                'id_cbg_ph' => 'cbg.id_cbg_ph',
                'id_outlet' => 'a.ship_to_party',
                'nama_outlet' => 'a.name_ship_to',
                'segment_dist' => 'a.desc_cust_grp3',
                'code_product' => 'a.material',
                'satuan' => 'a.sales_unit',
                'code_pelayanan' => 'a.code_pelayanan',
                'dec_pelayanan' => 'a.dec_pelayanan',
                'batch' => 'a.batch',
                'qty' => 'a.quantity',
                'hna' => 'a.unit_price_penjualan',
                'bruto' => 'a.nilai_bruto',
                'netto' => 'a.nilai_netto',
            ],
        ], $start, $end);
    }

    /**
     * Insert atau update data METD ke summary table di DWH.
     */
    private function insertMetdSummary(string $start, string $end): void
    {
        $cbgPhFallback = "COALESCE({$this->utf8Key('cbg.id_cbg_ph')}, {$this->utf8Key('m.kode_cbg_ph')})";

        $this->executeSummaryInsert([
            'source' => 'METD',
            'from_table' => 'selling_out_metd',
            'alias' => 'm',
            'date_col' => 'tgl_shipper',
            'filter_dist' => false,
            'reg_cbg_ph_override' => $cbgPhFallback,
            'select' => [
                'source_id' => 'm.id',
                'tgl_faktur' => 'm.tgl_shipper',
                'no_faktur' => 'm.no_faktur',
                'id_cbg' => 'm.kode_cbg_ph',
                'id_cbg_ph' => "{$cbgPhFallback} AS id_cbg_ph",
                'id_outlet' => 'm.id_outlet',
                'nama_outlet' => 'm.nama_outlet',
                'segment_dist' => 'm.segmen',
                'code_product' => 'm.kode_brg_metd',
                'satuan' => 'm.satuan_metd',
                'code_pelayanan' => "''",
                'dec_pelayanan' => "''",
                'batch' => 'm.batch_ph',
                'qty' => 'm.qty',
                'hna' => 'm.hna',
                'bruto' => 'm.bruto',
                'netto' => 'm.netto',
            ],
        ], $start, $end);
    }

    /**
     * Executing dynamic summary insert with standardized query template.
     */
    private function executeSummaryInsert(array $config, string $start, string $end): void
    {
        $source = $config['source'];
        $table = $config['from_table'];
        $alias = $config['alias'];
        $dateCol = $config['date_col'];
        $filterDist = $config['filter_dist'];
        $s = $config['select'];

        $segDistJoin  = $filterDist ? "{$this->utf8Key('seg.dist')} = '{$source}' AND " : '';
        $vpDistJoin   = $filterDist ? "{$this->utf8Key('vp.dist')} = '{$source}' AND " : '';
        $cbgDistJoin  = $filterDist ? "{$this->utf8Key('cbg.dist')} = '{$source}' AND " : '';
        $custDistJoin = $filterDist ? "{$this->utf8Key('TRIM(cd.dist)')} = '{$source}' AND " : '';

        $regCbgPhExpr = $config['reg_cbg_ph_override'] ?? $this->utf8Key('cbg.id_cbg_ph');

        $whereClause = "{$alias}.{$dateCol} BETWEEN ? AND ?";
        if (!empty($config['where'])) {
            $whereClause = "{$config['where']} AND {$whereClause}";
        }

        $colsSql = implode(",\n                ", array_map(fn($col) => $col === 'desc' ? '`desc`' : $col, self::COLUMNS));

        $onDuplicateSql = implode(",\n                ", array_map(function ($col) {
            if ($col === 'updated_at') {
                return 'updated_at = NOW()';
            }
            $colName = $col === 'desc' ? '`desc`' : $col;
            return "{$colName} = VALUES({$colName})";
        }, array_filter(self::COLUMNS, fn($col) => !in_array($col, ['source_table', 'source_id', 'created_at'], true))));

        $idCbgPhSelect = str_contains($s['id_cbg_ph'], ' AS ') ? $s['id_cbg_ph'] : "{$s['id_cbg_ph']} AS id_cbg_ph";

        $sql = "INSERT INTO " . self::SUMMARY_TABLE . " (
                {$colsSql}
            )
            SELECT
                '{$source}' AS source_table,
                {$s['source_id']} AS source_id,
                {$s['tgl_faktur']} AS tgl_faktur,
                {$s['no_faktur']} AS no_faktur,
                '{$source}' AS dist,
                {$s['id_cbg']} AS id_cbg,
                {$idCbgPhSelect},
                {$s['id_outlet']} AS id_outlet,
                cd.id_konversi AS id_konversi,
                {$s['nama_outlet']} AS nama_outlet,
                {$s['segment_dist']} AS segment_dist,
                seg.segment_mi AS segment_mi,
                {$s['code_product']} AS code_product,
                vp.pt_ph AS code_prod_ph,
                vp.pt_desc AS `desc`,
                {$s['satuan']} AS satuan,
                (CASE WHEN vp.pilar_sub = 'GENERIK' THEN 'OGB' ELSE vp.pilar_sub END) AS pilar_sub,
                {$s['code_pelayanan']} AS code_pelayanan,
                {$s['dec_pelayanan']} AS dec_pelayanan,
                sub.divisi AS divisi,
                sub.sub_divisi AS sub_divisi,
                reg.regional AS regional,
                {$s['batch']} AS batch,
                {$s['qty']} AS qty,
                {$s['hna']} AS hna,
                {$s['bruto']} AS bruto,
                {$s['netto']} AS netto,
                NOW() AS created_at,
                NOW() AS updated_at

            FROM {$table} AS {$alias}

            LEFT JOIN crm_mst_cust_dist AS cd
                ON {$custDistJoin}{$this->utf8Key('TRIM(cd.id_outlet)')} = {$this->utf8Key("TRIM({$s['id_outlet']})")}
                AND {$this->utf8Key('TRIM(cd.id_cbg)')} = {$this->utf8Key("TRIM({$s['id_cbg']})")}

            LEFT JOIN mst_segment AS seg
                ON {$segDistJoin}{$this->utf8Key('seg.segment_dist')} = {$this->utf8Key($s['segment_dist'])}

            LEFT JOIN v_pt_mstr_dist AS vp
                ON {$vpDistJoin}{$this->utf8Key('vp.pt_dist')} = {$this->utf8Key($s['code_product'])}

            LEFT JOIN crm_mst_sub_div AS sub
                ON {$this->utf8Key('sub.segment_mi')} = {$this->utf8Key('seg.segment_mi')}
                AND {$this->utf8Key('sub.pilar_sub')} = {$this->utf8Key('vp.pilar_sub')}
                AND sub.is_active = 1

            LEFT JOIN mst_cbg_dist_new AS cbg
                ON {$cbgDistJoin}{$this->utf8Key('cbg.kode_cbg_dist')} = {$this->utf8Key($s['id_cbg'])}

            LEFT JOIN crm_mst_regional AS reg
                ON {$this->utf8Key('reg.sub_divisi')} = {$this->utf8Key('sub.sub_divisi')}
                AND {$this->utf8Key('reg.id_cbg_ph')} = {$regCbgPhExpr}
                AND reg.is_active = 1

            WHERE {$whereClause}

            ON DUPLICATE KEY UPDATE
                {$onDuplicateSql}";

        DB::connection(self::CONN_DWH)->transaction(function () use ($source, $start, $end, $sql) {
            DB::connection(self::CONN_DWH)->table(self::SUMMARY_TABLE)
                ->where(function ($q) use ($source) {
                    $q->where('dist', $source)
                      ->orWhere('source_table', $source);
                })
                ->whereBetween('tgl_faktur', [$start, $end])
                ->delete();

            DB::connection(self::CONN_DWH)->statement($sql, [$start, $end]);
        });
    }
}
