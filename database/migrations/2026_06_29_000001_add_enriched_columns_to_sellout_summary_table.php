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
        if (!Schema::connection($this->connection)->hasTable('crm_sellout_summary')) {
            return;
        }

        Schema::connection($this->connection)->table('crm_sellout_summary', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'id_cbg_ph')) {
                $table->string('id_cbg_ph', 50)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'segment_dist')) {
                $table->string('segment_dist', 100)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'segment_mi')) {
                $table->string('segment_mi', 100)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'code_prod_ph')) {
                $table->string('code_prod_ph', 50)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'desc')) {
                $table->string('desc', 255)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'pilar_sub')) {
                $table->string('pilar_sub', 100)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'divisi')) {
                $table->string('divisi', 100)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'sub_divisi')) {
                $table->string('sub_divisi', 100)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'regional')) {
                $table->string('regional', 100)->nullable();
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'qty')) {
                $table->decimal('qty', 20, 4)->default(0);
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'hna')) {
                $table->decimal('hna', 20, 4)->default(0);
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_sellout_summary', 'bruto')) {
                $table->decimal('bruto', 20, 4)->default(0);
            }
        });

        $this->addIndexIfColumnsExist('crm_sellout_summary', ['tgl_faktur', 'code_prod_ph'], 'idx_summary_period_prod_ph');
        $this->addIndexIfColumnsExist('crm_sellout_summary', ['tgl_faktur', 'pilar_sub'], 'idx_summary_period_pilar');
        $this->addIndexIfColumnsExist('crm_sellout_summary', ['tgl_faktur', 'id_outlet', 'id_cbg', 'code_prod_ph', 'dist'], 'idx_summary_history_period_outlet_ph');
        $this->addIndexIfColumnsExist('crm_sellout_summary', ['tgl_faktur', 'divisi', 'regional'], 'idx_summary_period_divisi_regional');
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable('crm_sellout_summary')) {
            return;
        }

        $this->dropIndexIfExists('crm_sellout_summary', 'idx_summary_period_prod_ph');
        $this->dropIndexIfExists('crm_sellout_summary', 'idx_summary_period_pilar');
        $this->dropIndexIfExists('crm_sellout_summary', 'idx_summary_history_period_outlet_ph');
        $this->dropIndexIfExists('crm_sellout_summary', 'idx_summary_period_divisi_regional');

        Schema::connection($this->connection)->table('crm_sellout_summary', function (Blueprint $table) {
            foreach ([
                'id_cbg_ph',
                'segment_dist',
                'segment_mi',
                'code_prod_ph',
                'desc',
                'pilar_sub',
                'divisi',
                'sub_divisi',
                'regional',
                'qty',
                'hna',
                'bruto',
            ] as $column) {
                if (Schema::connection($this->connection)->hasColumn('crm_sellout_summary', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
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
