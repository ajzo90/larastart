<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLockToDataStructuresListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('larastart_data_structure_list_meta', function (Blueprint $table) {
            $table->tinyInteger("locked")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('larastart_data_structure_list_meta', function (Blueprint $table) {
            $table->dropColumn("locked");
        });
    }
}
