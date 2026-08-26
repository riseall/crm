<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration berjalan di koneksi db_dwh
     * Tabel crm_top_marketing menyimpan data target marketing bulanan
     */
    protected $connection = 'db_dwh';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('crm_top_marketing')) {
            Schema::connection($this->connection)->create('crm_top_marketing', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tahun', 10)->index();
                $table->unsignedTinyInteger('bulan')->index();
                $table->string('divisi', 100)->nullable()->index();
                $table->string('region', 100)->nullable()->index();
                $table->string('kode_ph', 50)->nullable()->index();
                $table->string('nama_prod', 255)->nullable();
                $table->string('pilar', 100)->nullable()->index();
                $table->string('pm', 150)->nullable();
                $table->string('mr', 150)->nullable();
                $table->string('am', 150)->nullable();
                $table->string('sam', 150)->nullable();
                $table->string('rm', 150)->nullable();
                $table->string('gm', 150)->nullable();
                $table->string('segment', 100)->nullable();
                $table->string('cbg_new', 100)->nullable();
                $table->decimal('unit', 20, 4)->default(0);
                $table->decimal('val_net', 20, 4)->default(0);
                $table->timestamps();

                $table->index(['tahun', 'bulan'], 'idx_top_marketing_period');
                $table->index(['divisi', 'region'], 'idx_top_marketing_divisi_region');
            });

            return;
        }

        Schema::connection($this->connection)->table('crm_top_marketing', function (Blueprint $table) {
            $stringColumns = [
                'divisi' => 100,
                'region' => 100,
                'kode_ph' => 50,
                'nama_prod' => 255,
                'pilar' => 100,
                'pm' => 150,
                'mr' => 150,
                'am' => 150,
                'sam' => 150,
                'rm' => 150,
                'gm' => 150,
                'segment' => 100,
                'cbg_new' => 100,
            ];

            foreach ($stringColumns as $column => $length) {
                if (!Schema::connection($this->connection)->hasColumn('crm_top_marketing', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_top_marketing', 'unit')) {
                $table->decimal('unit', 20, 4)->default(0);
            }

            if (!Schema::connection($this->connection)->hasColumn('crm_top_marketing', 'val_net')) {
                $table->decimal('val_net', 20, 4)->default(0);
            }
        });

        $this->addIndexIfColumnsExist('crm_top_marketing', ['tahun', 'bulan'], 'idx_top_marketing_period');
        $this->addIndexIfColumnsExist('crm_top_marketing', ['divisi', 'region'], 'idx_top_marketing_divisi_region');
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable('crm_top_marketing')) {
            return;
        }

        Schema::connection($this->connection)->table('crm_top_marketing', function (Blueprint $table) {
            foreach (['gm', 'rm', 'sam', 'am', 'mr'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('crm_top_marketing', $column)) {
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
};
