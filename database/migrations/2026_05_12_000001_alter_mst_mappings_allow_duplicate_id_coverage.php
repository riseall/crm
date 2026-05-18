<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlterMstMappingsAllowDuplicateIdCoverage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ($this->hasForeignKey('mst_outlets', 'mst_outlets_id_coverage_foreign')) {
            Schema::table('mst_outlets', function (Blueprint $table) {
                $table->dropForeign(['id_coverage']);
            });
        }

        if ($this->hasForeignKey('mst_products', 'mst_products_id_coverage_foreign')) {
            Schema::table('mst_products', function (Blueprint $table) {
                $table->dropForeign(['id_coverage']);
            });
        }

        if (!Schema::hasColumn('mst_mappings', 'id')) {
            DB::statement('ALTER TABLE mst_mappings DROP PRIMARY KEY');
            DB::statement('ALTER TABLE mst_mappings ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        }

        if (!$this->hasIndex('mst_mappings', 'idx_mst_mappings_id_coverage')) {
            DB::statement('ALTER TABLE mst_mappings ADD INDEX idx_mst_mappings_id_coverage (id_coverage)');
        }

        if (!$this->hasIndex('mst_mappings', 'idx_mst_mappings_id_coverage_active')) {
            DB::statement('ALTER TABLE mst_mappings ADD INDEX idx_mst_mappings_id_coverage_active (id_coverage, is_active)');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE mst_mappings DROP INDEX idx_mst_mappings_id_coverage_active');
        DB::statement('ALTER TABLE mst_mappings DROP INDEX idx_mst_mappings_id_coverage');
        DB::statement('ALTER TABLE mst_mappings DROP COLUMN id');
        DB::statement('ALTER TABLE mst_mappings ADD PRIMARY KEY (id_coverage)');

        Schema::table('mst_outlets', function (Blueprint $table) {
            $table->foreign('id_coverage')->references('id_coverage')->on('mst_mappings');
        });

        Schema::table('mst_products', function (Blueprint $table) {
            $table->foreign('id_coverage')->references('id_coverage')->on('mst_mappings');
        });
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        $database = DB::getDatabaseName();

        return count(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $constraint, 'FOREIGN KEY']
        )) > 0;
    }

    private function hasIndex(string $table, string $index): bool
    {
        return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])) > 0;
    }
}
