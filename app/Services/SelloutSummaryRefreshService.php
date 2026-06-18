<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SelloutSummaryRefreshService
{
    private const CONN_DWH = 'db_dwh';
    private const SUMMARY_TABLE = 'crm_sellout_summary';

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
    public function refreshSummaryRange(string $from, string $to): void
    {
        [$start, $end] = $this->normalizeDateRange([$from, $to]);

        $this->insertKftdSummary($start, $end);
        $this->insertAamSummary($start, $end);
        $this->insertMetdSummary($start, $end);
    }

    /**
     * Insert atau update data KFTD ke summary table di DWH.
     */
    private function insertKftdSummary(string $start, string $end): void
    {
        $segSegment = $this->utf8Key('seg.segment_dist');
        $kSegment = $this->utf8Key('k.desc_cust_grp4');
        $vpProduct = $this->utf8Key('vp.pt_dist');
        $kProduct = $this->utf8Key('k.material');
        $subSegment = $this->utf8Key('sub.segment_mi');
        $segSegmentMi = $this->utf8Key('seg.segment_mi');
        $subPilar = $this->utf8Key('sub.pilar_sub');
        $vpPilar = $this->utf8Key('vp.pilar_sub');
        $cbgDistId = $this->utf8Key('cbg.kode_cbg_dist');
        $kCbg = $this->utf8Key('k.sales_office');
        $regSubDivisi = $this->utf8Key('reg.sub_divisi');
        $subSubDivisi = $this->utf8Key('sub.sub_divisi');
        $regCbgPh = $this->utf8Key('reg.id_cbg_ph');
        $cbgPh = $this->utf8Key('cbg.id_cbg_ph');

        DB::connection(self::CONN_DWH)->statement(
            "INSERT INTO " . self::SUMMARY_TABLE . " (
                source_table,
                source_id,
                tgl_faktur,
                dist,
                id_outlet,
                id_cbg,
                id_cbg_ph,
                segment_dist,
                segment_mi,
                code_product,
                pilar_sub,
                divisi,
                sub_divisi,
                regional,
                netto,
                created_at,
                updated_at
            )
            SELECT
                'KFTD' AS source_table,
                k.id_selling_out AS source_id,
                k.posting_date AS tgl_faktur,
                'KFTD' AS dist,
                k.ship_to_party AS id_outlet,
                k.sales_office AS id_cbg,
                cbg.id_cbg_ph AS id_cbg_ph,
                k.desc_cust_grp4 AS segment_dist,
                seg.segment_mi AS segment_mi,
                k.material AS code_product,
                vp.pilar_sub AS pilar_sub,
                sub.divisi AS divisi,
                sub.sub_divisi AS sub_divisi,
                reg.regional AS regional,
                ((k.total_penjualan + k.total_discount) - (k.dis_zd02_amount + k.dis_zd04_amount)) AS netto,
                NOW() AS created_at,
                NOW() AS updated_at

            FROM selling_out_kftd AS k

            LEFT JOIN mst_segment AS seg
                ON {$segSegment} = {$kSegment}

            LEFT JOIN v_pt_mstr_dist AS vp
                ON {$vpProduct} = {$kProduct}

            LEFT JOIN crm_mst_sub_div AS sub
                ON {$subSegment} = {$segSegmentMi}
                AND {$subPilar} = {$vpPilar}
                AND sub.is_active = 1

            LEFT JOIN mst_cbg_dist_new AS cbg
                ON {$cbgDistId} = {$kCbg}

            LEFT JOIN crm_mst_regional AS reg
                ON {$regSubDivisi} = {$subSubDivisi}
                AND {$regCbgPh} = {$cbgPh}
                AND reg.is_active = 1

            WHERE k.principle = '7000000344'
                AND k.posting_date BETWEEN ? AND ?

            ON DUPLICATE KEY UPDATE
                dist = VALUES(dist),
                tgl_faktur = VALUES(tgl_faktur),
                id_outlet = VALUES(id_outlet),
                id_cbg = VALUES(id_cbg),
                id_cbg_ph = VALUES(id_cbg_ph),
                code_product = VALUES(code_product),
                segment_dist = VALUES(segment_dist),
                segment_mi = VALUES(segment_mi),
                pilar_sub = VALUES(pilar_sub),
                divisi = VALUES(divisi),
                sub_divisi = VALUES(sub_divisi),
                regional = VALUES(regional),
                netto = VALUES(netto),
                updated_at = NOW()",
            [$start, $end]
        );
    }

    /**
     * Insert atau update data AAM ke summary table di DWH.
     */
    private function insertAamSummary(string $start, string $end): void
    {
        $segSegment = $this->utf8Key('seg.segment_dist');
        $aSegment = $this->utf8Key('a.desc_cust_grp3');
        $vpProduct = $this->utf8Key('vp.pt_dist');
        $aProduct = $this->utf8Key('a.material');
        $subSegment = $this->utf8Key('sub.segment_mi');
        $segSegmentMi = $this->utf8Key('seg.segment_mi');
        $subPilar = $this->utf8Key('sub.pilar_sub');
        $vpPilar = $this->utf8Key('vp.pilar_sub');
        $cbgDistId = $this->utf8Key('cbg.kode_cbg_dist');
        $aCbg = $this->utf8Key('a.sales_office');
        $regSubDivisi = $this->utf8Key('reg.sub_divisi');
        $subSubDivisi = $this->utf8Key('sub.sub_divisi');
        $regCbgPh = $this->utf8Key('reg.id_cbg_ph');
        $cbgPh = $this->utf8Key('cbg.id_cbg_ph');

        DB::connection(self::CONN_DWH)->statement(
            "INSERT INTO " . self::SUMMARY_TABLE . " (
                source_table,
                source_id,
                tgl_faktur,
                dist,
                id_outlet,
                id_cbg,
                id_cbg_ph,
                segment_dist,
                segment_mi,
                code_product,
                pilar_sub,
                divisi,
                sub_divisi,
                regional,
                netto,
                created_at,
                updated_at
            )
            SELECT
                'AAM' AS source_table,
                a.id AS source_id,
                a.posting_date AS tgl_faktur,
                'AAM' AS dist,
                a.ship_to_party AS id_outlet,
                a.sales_office AS id_cbg,
                cbg.id_cbg_ph AS id_cbg_ph,
                a.desc_cust_grp3 AS segment_dist,
                seg.segment_mi AS segment_mi,
                a.material AS code_product,
                vp.pilar_sub AS pilar_sub,
                sub.divisi AS divisi,
                sub.sub_divisi AS sub_divisi,
                reg.regional AS regional,
                a.nilai_netto AS netto,
                NOW() AS created_at,
                NOW() AS updated_at

            FROM selling_out_aam AS a

            LEFT JOIN mst_segment AS seg
                ON {$segSegment} = {$aSegment}

            LEFT JOIN v_pt_mstr_dist AS vp
                ON {$vpProduct} = {$aProduct}

            LEFT JOIN crm_mst_sub_div AS sub
                ON {$subSegment} = {$segSegmentMi}
                AND {$subPilar} = {$vpPilar}
                AND sub.is_active = 1

            LEFT JOIN mst_cbg_dist_new AS cbg
                ON {$cbgDistId} = {$aCbg}

            LEFT JOIN crm_mst_regional AS reg
                ON {$regSubDivisi} = {$subSubDivisi}
                AND {$regCbgPh} = {$cbgPh}
                AND reg.is_active = 1

            WHERE a.posting_date BETWEEN ? AND ?

            ON DUPLICATE KEY UPDATE
                dist = VALUES(dist),
                tgl_faktur = VALUES(tgl_faktur),
                id_outlet = VALUES(id_outlet),
                id_cbg = VALUES(id_cbg),
                id_cbg_ph = VALUES(id_cbg_ph),
                code_product = VALUES(code_product),
                segment_dist = VALUES(segment_dist),
                segment_mi = VALUES(segment_mi),
                pilar_sub = VALUES(pilar_sub),
                divisi = VALUES(divisi),
                sub_divisi = VALUES(sub_divisi),
                regional = VALUES(regional),
                netto = VALUES(netto),
                updated_at = NOW()",
            [$start, $end]
        );
    }

    /**
     * Insert atau update data METD ke summary table di DWH.
     */
    private function insertMetdSummary(string $start, string $end): void
    {
        $segSegment = $this->utf8Key('seg.segment_dist');
        $mSegment = $this->utf8Key('m.segmen');
        $vpProduct = $this->utf8Key('vp.pt_dist');
        $mProduct = $this->utf8Key('m.kode_brg_metd');
        $subSegment = $this->utf8Key('sub.segment_mi');
        $segSegmentMi = $this->utf8Key('seg.segment_mi');
        $subPilar = $this->utf8Key('sub.pilar_sub');
        $vpPilar = $this->utf8Key('vp.pilar_sub');
        $cbgDistId = $this->utf8Key('cbg.kode_cbg_dist');
        $mCbg = $this->utf8Key('m.kode_cbg_ph');
        $regSubDivisi = $this->utf8Key('reg.sub_divisi');
        $subSubDivisi = $this->utf8Key('sub.sub_divisi');
        $regCbgPh = $this->utf8Key('reg.id_cbg_ph');
        $cbgPhFallback = "COALESCE({$this->utf8Key('cbg.id_cbg_ph')}, {$mCbg})";

        DB::connection(self::CONN_DWH)->statement(
            "INSERT INTO " . self::SUMMARY_TABLE . " (
            source_table,
            source_id,
            tgl_faktur,
            dist,
            id_outlet,
            id_cbg,
            id_cbg_ph,
            segment_dist,
            segment_mi,
            code_product,
            pilar_sub,
            divisi,
            sub_divisi,
            regional,
            netto,
            created_at,
            updated_at
        )
        SELECT
            'METD' AS source_table,
            m.id AS source_id,
            m.tgl_shipper AS tgl_faktur,
            'METD' AS dist,
            m.id_outlet AS id_outlet,
            m.kode_cbg_ph AS id_cbg,
            {$cbgPhFallback} AS id_cbg_ph,
            m.segmen AS segment_dist,
            seg.segment_mi AS segment_mi,
            m.kode_brg_metd AS code_product,
            vp.pilar_sub AS pilar_sub,
            sub.divisi AS divisi,
            sub.sub_divisi AS sub_divisi,
            reg.regional AS regional,
            m.netto AS netto,
            NOW() AS created_at,
            NOW() AS updated_at

        FROM selling_out_metd AS m

        LEFT JOIN mst_segment AS seg
            ON {$segSegment} = {$mSegment}

        LEFT JOIN v_pt_mstr_dist AS vp
            ON {$vpProduct} = {$mProduct}

        LEFT JOIN crm_mst_sub_div AS sub
            ON {$subSegment} = {$segSegmentMi}
            AND {$subPilar} = {$vpPilar}
            AND sub.is_active = 1

        LEFT JOIN mst_cbg_dist_new AS cbg
            ON {$cbgDistId} = {$mCbg}

        LEFT JOIN crm_mst_regional AS reg
            ON {$regSubDivisi} = {$subSubDivisi}
            AND {$regCbgPh} = {$cbgPhFallback}
            AND reg.is_active = 1

        WHERE m.tgl_shipper BETWEEN ? AND ?

        ON DUPLICATE KEY UPDATE
            tgl_faktur = VALUES(tgl_faktur),
            dist = VALUES(dist),
            id_outlet = VALUES(id_outlet),
            id_cbg = VALUES(id_cbg),
            id_cbg_ph = VALUES(id_cbg_ph),
            code_product = VALUES(code_product),
            segment_dist = VALUES(segment_dist),
            segment_mi = VALUES(segment_mi),
            pilar_sub = VALUES(pilar_sub),
            divisi = VALUES(divisi),
            sub_divisi = VALUES(sub_divisi),
            regional = VALUES(regional),
            netto = VALUES(netto),
            updated_at = NOW()",
            [$start, $end]
        );
    }
}
