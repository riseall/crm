<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mst_products', function (Blueprint $table) {
            $table->id('id');
            $table->string('pt_ph');
            $table->string('id_coverage');
            $table->boolean('needs_replacement')->default(false); // flag butuh pengganti
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_coverage')->references('id_coverage')->on('mst_mappings');
            $table->unique(['pt_ph', 'id_coverage']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mst_products');
    }
}
