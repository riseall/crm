<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah index pada tabel-tabel yang dipakai di sellout query.
 * Jalankan: php artisan migrate
 */
return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------
        // selling_out_kftd
        // ----------------------------------------------------------
        // Schema::table('selling_out_kftd', function (Blueprint $table) {
        //     // Filter tanggal di selloutUnion
        //     if (!$this->hasIndex('selling_out_kftd', 'idx_kftd_posting_date')) {
        //         $table->index('posting_date', 'idx_kftd_posting_date');
        //     }
        //     // Filter principle
        //     if (!$this->hasIndex('selling_out_kftd', 'idx_kftd_principle')) {
        //         $table->index('principle', 'idx_kftd_principle');
        //     }
        //     // Composite: paling sering difilter bersamaan
        //     if (!$this->hasIndex('selling_out_kftd', 'idx_kftd_principle_date')) {
        //         $table->index(['principle', 'posting_date'], 'idx_kftd_principle_date');
        //     }
        // });

        // ----------------------------------------------------------
        // selling_out_aam
        // // ----------------------------------------------------------
        // Schema::table('selling_out_aam', function (Blueprint $table) {
        //     if (!$this->hasIndex('selling_out_aam', 'idx_aam_posting_date')) {
        //         $table->index('posting_date', 'idx_aam_posting_date');
        //     }
        // });

        // ----------------------------------------------------------
        // selling_out_metd
        // ----------------------------------------------------------
        // Schema::table('selling_out_metd', function (Blueprint $table) {
        //     if (!$this->hasIndex('selling_out_metd', 'idx_metd_tgl_faktur')) {
        //         $table->index('tgl_faktur', 'idx_metd_tgl_faktur');
        //     }
        // });

        // ----------------------------------------------------------
        // crm_mst_mappings
        // ----------------------------------------------------------
        Schema::table('crm_mst_mappings', function (Blueprint $table) {
            // Filter periode aktif di baseQuery & outletHistoryQuery
            if (!$this->hasIndex('crm_mst_mappings', 'idx_mapping_valid_from')) {
                $table->index('valid_from', 'idx_mapping_valid_from');
            }
            if (!$this->hasIndex('crm_mst_mappings', 'idx_mapping_valid_to')) {
                $table->index('valid_to', 'idx_mapping_valid_to');
            }
            // Filter level di baseQuery
            if (!$this->hasIndex('crm_mst_mappings', 'idx_mapping_cov_level')) {
                $table->index('cov_level', 'idx_mapping_cov_level');
            }
            // JOIN ke mst_outlets
            if (!$this->hasIndex('crm_mst_mappings', 'idx_mapping_id_coverage')) {
                $table->index('id_coverage', 'idx_mapping_id_coverage');
            }
            // Filter is_active di OutletController->data()
            if (!$this->hasIndex('crm_mst_mappings', 'idx_mapping_is_active')) {
                $table->index('is_active', 'idx_mapping_is_active');
            }
        });

        // ----------------------------------------------------------
        // mst_outlets
        // ----------------------------------------------------------
        Schema::table('mst_outlets', function (Blueprint $table) {
            // JOIN dari sellout via id_outlet + id_cbg
            // if (!$this->hasIndex('mst_outlets', 'idx_outlet_id_outlet_cbg')) {
            //     $table->index(['id_outlet', 'id_cbg'], 'idx_outlet_id_outlet_cbg');
            // }
            // needs_replacement untuk filter di OutletController
            if (!$this->hasIndex('mst_outlets', 'idx_outlet_needs_replacement')) {
                $table->index('needs_replacement', 'idx_outlet_needs_replacement');
            }
        });
    }

    public function down(): void
    {
        Schema::table('selling_out_kftd', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_kftd_posting_date');
            $table->dropIndexIfExists('idx_kftd_principle');
            $table->dropIndexIfExists('idx_kftd_principle_date');
        });

        Schema::table('selling_out_aam', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_aam_posting_date');
        });

        Schema::table('selling_out_metd', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_metd_tgl_faktur');
        });

        Schema::table('crm_mst_mappings', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_mapping_valid_from');
            $table->dropIndexIfExists('idx_mapping_valid_to');
            $table->dropIndexIfExists('idx_mapping_cov_level');
            $table->dropIndexIfExists('idx_mapping_id_coverage');
            $table->dropIndexIfExists('idx_mapping_is_active');
        });

        Schema::table('mst_outlets', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_outlet_id_coverage');
            $table->dropIndexIfExists('idx_outlet_id_outlet_cbg');
            $table->dropIndexIfExists('idx_outlet_needs_replacement');
        });
    }

    /**
     * Cek apakah index sudah ada agar migration idempoten
     * (aman dijalankan ulang tanpa error duplikat index).
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
