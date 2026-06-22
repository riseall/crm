<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_dwh';

    public function up(): void
    {
        $this->addIndexIfColumnsExist('selling_out_kftd', ['principle', 'posting_date'], 'idx_refresh_kftd_principle_date');
        $this->addIndexIfColumnsExist('selling_out_aam', ['posting_date'], 'idx_refresh_aam_posting_date');
        $this->addIndexIfColumnsExist('selling_out_metd', ['tgl_shipper'], 'idx_refresh_metd_tgl_shipper');

        $this->addIndexIfColumnsExist('mst_segment', ['dist', 'segment_dist'], 'idx_refresh_segment_dist');
        $this->addIndexIfColumnsExist('mst_cbg_dist_new', ['dist', 'kode_cbg_dist'], 'idx_refresh_cbg_dist');
        $this->addIndexIfColumnsExist('crm_mst_sub_div', ['segment_mi', 'pilar_sub', 'is_active'], 'idx_refresh_sub_div_rule');
        $this->addIndexIfColumnsExist('crm_mst_regional', ['sub_divisi', 'id_cbg_ph', 'is_active'], 'idx_refresh_regional_rule');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('selling_out_kftd', 'idx_refresh_kftd_principle_date');
        $this->dropIndexIfExists('selling_out_aam', 'idx_refresh_aam_posting_date');
        $this->dropIndexIfExists('selling_out_metd', 'idx_refresh_metd_tgl_shipper');

        $this->dropIndexIfExists('mst_segment', 'idx_refresh_segment_dist');
        $this->dropIndexIfExists('mst_cbg_dist_new', 'idx_refresh_cbg_dist');
        $this->dropIndexIfExists('crm_mst_sub_div', 'idx_refresh_sub_div_rule');
        $this->dropIndexIfExists('crm_mst_regional', 'idx_refresh_regional_rule');
    }

    private function addIndexIfColumnsExist(string $table, array $columns, string $indexName): void
    {
        if (!Schema::connection($this->connection)->hasTable($table) || $this->hasIndex($table, $indexName)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::connection($this->connection)->hasColumn($table, $column)) {
                return;
            }
        }

        Schema::connection($this->connection)->table($table, function (Blueprint $table) use ($columns, $indexName) {
            $table->index($columns, $indexName);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (!Schema::connection($this->connection)->hasTable($table)) {
            return false;
        }

        $indexes = DB::connection($this->connection)
            ->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::connection($this->connection)->table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }
};
