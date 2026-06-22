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
        Schema::connection($this->connection)->table('crm_sellout_summary', function (Blueprint $table) {
            if (!$this->hasIndex('crm_sellout_summary', 'idx_summary_history_period_outlet_product')) {
                $table->index(
                    ['tgl_faktur', 'id_outlet', 'id_cbg', 'code_product', 'dist'],
                    'idx_summary_history_period_outlet_product'
                );
            }
        });

        Schema::connection($this->connection)->table('crm_mst_outlets', function (Blueprint $table) {
            if (!$this->hasIndex('crm_mst_outlets', 'idx_outlets_history_lookup')) {
                $table->index(['id_outlet', 'deleted_at', 'id_coverage'], 'idx_outlets_history_lookup');
            }
        });

        Schema::connection($this->connection)->table('crm_mst_mappings', function (Blueprint $table) {
            if (!$this->hasIndex('crm_mst_mappings', 'idx_mappings_history_lookup')) {
                $table->index(
                    ['id_coverage', 'deleted_at', 'cov_level', 'valid_from', 'valid_to'],
                    'idx_mappings_history_lookup'
                );
            }
        });

        Schema::connection($this->connection)->table('mst_cust_dist', function (Blueprint $table) {
            if (!$this->hasIndex('mst_cust_dist', 'idx_cust_dist_history_lookup')) {
                $table->index(['id_outlet', 'id_cbg_dist'], 'idx_cust_dist_history_lookup');
            }
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('crm_sellout_summary', 'idx_summary_history_period_outlet_product');
        $this->dropIndexIfExists('crm_mst_outlets', 'idx_outlets_history_lookup');
        $this->dropIndexIfExists('crm_mst_mappings', 'idx_mappings_history_lookup');
        $this->dropIndexIfExists('mst_cust_dist', 'idx_cust_dist_history_lookup');
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::connection($this->connection)
            ->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->hasIndex($table, $indexName)) {
            return;
        }

        DB::connection($this->connection)->statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
    }
};
