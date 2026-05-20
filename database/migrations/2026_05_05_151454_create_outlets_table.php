<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOutletsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mst_outlets', function (Blueprint $table) {
            $table->id('id');
            $table->string('id_outlet');
            $table->string('id_coverage');
            $table->boolean('needs_replacement')->default(false); // flag butuh pengganti
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_coverage')->references('id_coverage')->on('crm_mst_mappings');
            $table->unique(['id_outlet', 'id_coverage']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mst_outlets');
    }
}
