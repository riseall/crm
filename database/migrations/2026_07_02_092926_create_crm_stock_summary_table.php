<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_stock_summary', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->nullable();
            $table->string('pt_ph', 100)->nullable();
            $table->string('pt_dist', 100)->nullable();
            $table->string('pt_desc', 255)->nullable();
            $table->string('cabang', 100)->nullable();
            $table->decimal('stock', 15, 2)->default(0);
            $table->decimal('ptprice', 15, 2)->default(0);
            $table->decimal('value', 15, 2)->default(0);
            $table->string('pilar', 100)->nullable();
            $table->date('exp_date')->nullable();
            $table->string('kelompok_ed', 50)->nullable();
            $table->timestamps();

            // Indexing untuk optimalisasi filter s.source, s.pilar, s.cabang, s.kelompok_ed
            $table->index('source');
            $table->index('pilar');
            $table->index('cabang');
            $table->index('kelompok_ed');
            
            // Index tambahan untuk kebutuhan searching DataTables & COUNT(DISTINCT)
            $table->index('pt_ph');
            $table->index('pt_dist');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_stock_summary');
    }
};