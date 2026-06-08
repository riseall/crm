<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_dwh';

    public function up(): void
    {
        Schema::connection($this->connection)->create('stock_ph_in_gpj', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('row_key', 40)->unique();
            $table->string('pt_part', 50)->index();
            $table->string('pt_desc', 255)->nullable();
            $table->decimal('stock', 20, 6)->default(0);
            $table->string('pt_um', 20)->nullable();
            $table->string('ld_loc', 50)->nullable()->index();
            $table->string('ld_lot', 100)->nullable()->index();
            $table->string('pt_promo', 50)->nullable()->index();
            $table->string('pilar', 50)->nullable()->index();
            $table->string('picscode', 50)->nullable();
            $table->decimal('ptprice', 20, 6)->default(0);
            $table->decimal('umconv', 20, 6)->default(0);
            $table->decimal('rharga', 20, 6)->default(0);
            $table->string('cathrg', 255)->nullable();
            $table->date('expdt')->nullable()->index();
            $table->string('kelompok_ed', 50)->nullable()->index();
            $table->timestamps();

            $table->index(['pt_part', 'ld_loc', 'ld_lot', 'expdt'], 'idx_stock_gpj_identity');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('stock_ph_in_gpj');
    }
};
