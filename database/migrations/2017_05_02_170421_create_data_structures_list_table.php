<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDataStructuresListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('larastart_data_structure_list', function (Blueprint $table) {
            $table->unsignedSmallInteger('list_id')->index();
            $table->unsignedInteger("key")->index();
            $table->unique(["list_id", "key"]);
        });

        Schema::create('larastart_data_structure_list_meta', function (Blueprint $table) {
            $table->increments('id');
            $table->string("key")->unique();
            $table->unsignedInteger("updated_at");
            $table->unsignedInteger("minutes")->default(60 * 24 * 1); // 2 days
            $table->boolean("forever")->default(false);
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
        Schema::dropIfExists('larastart_data_structure_list_meta');
    }
}
