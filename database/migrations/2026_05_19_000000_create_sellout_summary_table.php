<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration ini berjalan di koneksi db_dwh
     * Tabel crm_sellout_summary menyimpan data transaksi inti dari semua source
     */
    protected $connection = 'db_dwh';

    public function up(): void
    {
        Schema::connection($this->connection)->create('crm_sellout_summary', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source_table', 10)->comment('KFTD, AAM, atau METD');
            $table->unsignedBigInteger('source_id')->comment('ID dari source table');
            $table->dateTime('tgl_faktur')->index()->comment('Tanggal faktur/transaksi');
            $table->string('dist', 10)->index()->comment('Distributor code');
            $table->string('id_outlet', 50)->index()->comment('Outlet ID');
            $table->string('id_cbg', 50)->nullable()->comment('CBG/Sales Office');
            $table->string('code_product', 50)->nullable()->comment('Product code');
            $table->decimal('netto', 20, 4)->default(0)->comment('Net sales amount');
            $table->timestamps();

            // Unique constraint untuk prevent duplicate entries
            $table->unique(['source_table', 'source_id'], 'uniq_sellout_source');

            // Composite index untuk query performance
            $table->index(['tgl_faktur', 'dist'], 'idx_sellout_basic');
            $table->index(['tgl_faktur', 'id_outlet'], 'idx_sellout_outlet');
            $table->index(['tgl_faktur', 'code_product'], 'idx_sellout_product');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('crm_sellout_summary');
    }
};
