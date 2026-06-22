<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crm_mst_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('id_coverage')->primary();
            $table->string('cov_desc')->nullable();
            $table->string('cov_divisi')->nullable();
            $table->string('cov_ket_cbg')->nullable();
            $table->enum('cov_pilar', ['ETHICAL', 'ETHICOM', 'ETHIPROM', 'OGB', 'OTC'])->nullable();
            $table->unsignedTinyInteger('cov_level')->nullable();
            $table->enum('cov_level_desc', ['MR/SR', 'AM', 'SAM', 'RM', 'PM', 'GM'])->nullable();
            $table->string('cov_nik')->nullable();
            $table->enum('tanggung_jwb_pilar', ['Single', 'Combo'])->default('Single');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crm_mst_mappings');
    }
}
