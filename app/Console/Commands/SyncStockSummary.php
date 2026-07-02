<?php

namespace App\Console\Commands;

use App\Models\StockSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Log;

class SyncStockSummary extends Command
{
    protected $signature = 'stock:refresh-summary';
    protected $description = 'Sinkronisasi data langsung dari tabel-tabel DWH ke tabel lokal crm_stock_summary via Truncate';

    private string $dwh = 'db_dwh'; // Nama koneksi DWH Anda

    public function handle()
    {
        $this->info('Memulai sinkronisasi langsung dari tabel DWH...');
        
        try {
            // 1. Kosongkan tabel lokal terlebih dahulu
            StockSummary::truncate();

            // 2. Jalankan sinkronisasi per masing-masing Source
            $this->syncGPJ();
            $this->syncAAM();
            $this->syncKFTD();
            $this->syncMETD();

            $this->info('Sinkronisasi seluruh source selesai dengan sukses!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            Log::error('Gagal sinkronisasi stock summary: ' . $e->getMessage());
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * SINKRONISASI SOURCE: GPJ
     */
    private function syncGPJ()
    {
        $this->info('Mengonversi data GPJ...');

        $query = DB::connection($this->dwh)
            ->table('stock_ph_in_gpj as sg')
            ->leftJoin(DB::raw('(
                select pt_ph, max(pilar_sub) as pilar_sub 
                from v_pt_mstr_dist 
                group by pt_ph
            ) as vp'), 'vp.pt_ph', '=', 'sg.pt_part')
            ->selectRaw("
                'GPJ' AS source,
                sg.pt_part AS pt_ph,
                '' AS pt_dist,
                sg.pt_desc AS pt_desc,
                'GPJ' AS cabang,
                sg.stock AS stock,
                sg.ptprice AS ptprice,
                sg.rharga AS value,
                (CASE WHEN vp.pilar_sub = 'GENERIK' THEN 'OGB' ELSE vp.pilar_sub END) AS pilar,
                sg.expdt AS exp_date,
                (CASE 
                    WHEN sg.expdt < CURDATE() THEN 'sudah_ed'
                    WHEN sg.expdt < (CURDATE() + INTERVAL 3 MONTH) THEN '1_3'
                    WHEN sg.expdt < (CURDATE() + INTERVAL 7 MONTH) THEN '4_7'
                    WHEN sg.expdt < (CURDATE() + INTERVAL 12 MONTH) THEN '8_12'
                    WHEN sg.expdt < (CURDATE() + INTERVAL 17 MONTH) THEN '13_17'
                    ELSE 'gt_18'
                END) AS kelompok_ed
            ")
            ->orderBy('sg.pt_part');

        $query->chunk(2000, function ($rows) {
            $this->insertToLocal($rows);
        });
    }

    /**
     * SINKRONISASI SOURCE: AAM
     */
    private function syncAAM()
    {
        $this->info('Mengonversi data AAM...');

        $query = DB::connection($this->dwh)
            ->table('stock_ph_in_aam as sa')
            ->leftJoin('v_pt_mstr_dist as vp', 'vp.pt_dist', '=', 'sa.material')
            ->leftJoin('mst_warehouse_aam as mw', 'mw.warehouse_code', '=', 'sa.plant')
            ->leftJoin('mst_cbg_dist_new as mc', 'mc.kode_cbg_dist', '=', 'mw.kode_cbg_aam')
            ->selectRaw("
                'AAM' AS source,
                vp.pt_ph AS pt_ph,
                sa.material AS pt_dist,
                vp.pt_desc AS pt_desc,
                (CASE WHEN mc.nama_cbg = 'JAKARTA 1' THEN 'AAM PUSAT' ELSE mc.nama_cbg END) AS cabang,
                sa.unrestricted_qty_per_um AS stock,
                COALESCE(vp.pt_price, 0) AS ptprice,
                (sa.unrestricted_qty_per_um * COALESCE(vp.pt_price, 0)) AS value,
                (CASE WHEN vp.pilar_sub = 'GENERIK' THEN 'OGB' ELSE vp.pilar_sub END) AS pilar,
                sa.sled AS exp_date,
                (CASE 
                    WHEN sa.sled < CURDATE() THEN 'sudah_ed'
                    WHEN sa.sled < (CURDATE() + INTERVAL 3 MONTH) THEN '1_3'
                    WHEN sa.sled < (CURDATE() + INTERVAL 7 MONTH) THEN '4_7'
                    WHEN sa.sled < (CURDATE() + INTERVAL 12 MONTH) THEN '8_12'
                    WHEN sa.sled < (CURDATE() + INTERVAL 17 MONTH) THEN '13_17'
                    ELSE 'gt_18'
                END) AS kelompok_ed
            ")
            ->orderBy('sa.material');

        $query->chunk(2000, function ($rows) {
            $this->insertToLocal($rows);
        });
    }

    /**
     * SINKRONISASI SOURCE: KFTD
     */
    private function syncKFTD()
    {
        $this->info('Mengonversi data KFTD...');

        $query = DB::connection($this->dwh)
            ->table('stock_ph_in_kftd as sk')
            ->leftJoin('v_pt_mstr_dist as vp', 'vp.pt_dist', '=', 'sk.material')
            ->leftJoin('mst_conv_qty_kftd as ck', 'ck.item_number', '=', 'vp.pt_ph')
            ->where('sk.principle', '7000000344')
            ->selectRaw("
                'KFTD' AS source,
                vp.pt_ph AS pt_ph,
                sk.material AS pt_dist,
                vp.pt_desc AS pt_desc,
                UPPER(TRIM(LEADING 'KFTD ' FROM sk.plant_desc)) AS cabang,
                (sk.unrestricted / COALESCE(NULLIF(ck.pcs_per_sku, 0), 1)) AS stock,
                COALESCE(vp.pt_price, 0) AS ptprice,
                ((sk.unrestricted / COALESCE(NULLIF(ck.pcs_per_sku, 0), 1)) * COALESCE(vp.pt_price, 0)) AS value,
                (CASE WHEN vp.pilar_sub = 'GENERIK' THEN 'OGB' ELSE vp.pilar_sub END) AS pilar,
                sk.sled_date AS exp_date,
                (CASE 
                    WHEN sk.sled_date < CURDATE() THEN 'sudah_ed'
                    WHEN sk.sled_date < (CURDATE() + INTERVAL 3 MONTH) THEN '1_3'
                    WHEN sk.sled_date < (CURDATE() + INTERVAL 7 MONTH) THEN '4_7'
                    WHEN sk.sled_date < (CURDATE() + INTERVAL 12 MONTH) THEN '8_12'
                    WHEN sk.sled_date < (CURDATE() + INTERVAL 17 MONTH) THEN '13_17'
                    ELSE 'gt_18'
                END) AS kelompok_ed
            ")
            ->orderBy('sk.material');

        $query->chunk(2000, function ($rows) {
            $this->insertToLocal($rows);
        });
    }

    /**
     * SINKRONISASI SOURCE: METD
     */
    private function syncMETD()
    {
        $this->info('Mengonversi data METD...');

        $query = DB::connection($this->dwh)
            ->table('stock_ph_in_metd as sm')
            ->leftJoin('v_pt_mstr_dist as vp', 'vp.pt_dist', '=', 'sm.kode_brg_metd')
            ->selectRaw("
                'METD' AS source,
                vp.pt_ph AS pt_ph,
                sm.kode_brg_metd AS pt_dist,
                vp.pt_desc AS pt_desc,
                'METD' AS cabang,
                sm.qty_selleable AS stock,
                COALESCE(vp.pt_price, 0) AS ptprice,
                (sm.qty_selleable * COALESCE(vp.pt_price, 0)) AS value,
                (CASE WHEN vp.pilar_sub = 'GENERIK' THEN 'OGB' ELSE vp.pilar_sub END) AS pilar,
                sm.expired_date AS exp_date,
                (CASE 
                    WHEN sm.expired_date < CURDATE() THEN 'sudah_ed'
                    WHEN sm.expired_date < (CURDATE() + INTERVAL 3 MONTH) THEN '1_3'
                    WHEN sm.expired_date < (CURDATE() + INTERVAL 7 MONTH) THEN '4_7'
                    WHEN sm.expired_date < (CURDATE() + INTERVAL 12 MONTH) THEN '8_12'
                    WHEN sm.expired_date < (CURDATE() + INTERVAL 17 MONTH) THEN '13_17'
                    ELSE 'gt_18'
                END) AS kelompok_ed
            ")
            ->orderBy('sm.kode_brg_metd');

        $query->chunk(2000, function ($rows) {
            $this->insertToLocal($rows);
        });
    }

    /**
     * HELPER: Fungsi untuk insert data hasil chunk ke tabel lokal crm_stock_summary
     */
    private function insertToLocal($rows)
    {
        $insertData = [];
        $now = now();

        foreach ($rows as $row) {
            $insertData[] = [
                'source'      => $row->source,
                'pt_ph'       => $row->pt_ph,
                'pt_dist'     => $row->pt_dist,
                'pt_desc'     => $row->pt_desc,
                'cabang'      => $row->cabang,
                'stock'       => $row->stock,
                'ptprice'     => $row->ptprice,
                'value'       => $row->value,
                'pilar'       => $row->pilar,
                'exp_date'    => $row->exp_date,
                'kelompok_ed' => $row->kelompok_ed,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        StockSummary::insert($insertData);
    }
}