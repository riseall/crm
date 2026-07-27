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

        if (!$this->hasIndex('crm_sellout_summary', 'uniq_sellout_source')) {
            // Hapus record duplikat jika ada, pertahankan id terbaru (id terbesar)
            DB::connection($this->connection)->statement("
                DELETE s1 FROM crm_sellout_summary s1
                INNER JOIN crm_sellout_summary s2 
                    ON s1.source_table = s2.source_table 
                    AND s1.source_id = s2.source_id 
                    AND s1.id < s2.id
            ");

            Schema::connection($this->connection)->table('crm_sellout_summary', function (Blueprint $table) {
                $table->unique(['source_table', 'source_id'], 'uniq_sellout_source');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable('crm_sellout_summary')) {
            return;
        }

        if ($this->hasIndex('crm_sellout_summary', 'uniq_sellout_source')) {
            Schema::connection($this->connection)->table('crm_sellout_summary', function (Blueprint $table) {
                $table->dropUnique('uniq_sellout_source');
            });
        }
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
};
