<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RefactorDataStructuresListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('larastart_data_structure_list');

        Schema::create('larastart_data_structure_list', function (Blueprint $table) {
            $table->unsignedMediumInteger('list_id');
            $table->unsignedInteger("key");
            $table->primary(["list_id", "key"]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('larastart_data_structure_list');

        Schema::create('larastart_data_structure_list', function (Blueprint $table) {
            $table->unsignedSmallInteger('list_id')->index();
            $table->unsignedInteger("key")->index();
            $table->unique(["list_id", "key"]);
        });
    }
}
