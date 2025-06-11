<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */

    public function up()
    {
        Schema::connection('sqlsrv_wms')->create('receive_p_l_s', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_doc', 50);
            $table->string('item_code', 50);
            $table->date('delivery_date');
            $table->decimal('delivery_quantity', 18, 2);
            $table->decimal('ship_quantity', 18, 2);
            $table->string('pallet', 35);
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->string('created_by', 9);
            $table->string('updated_by', 9)->nullable();
            $table->string('deleted_by', 9)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('receive_p_l_s');
    }
};
