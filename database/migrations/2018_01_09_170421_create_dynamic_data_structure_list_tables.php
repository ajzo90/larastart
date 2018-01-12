<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDynamicDataStructureListTables extends Migration
{

    // daily lists is just caching. Truncate the next day on cleanup, to always use a fresh list every day.
    // events should allow for append?
    // dont cache the daily lists

    private static $lists = ['daily_cache0', 'daily_cache1', 'daily_cache2', 'extended_cache'];

    public function up()
    {

        Schema::table('larastart_data_structure_list_meta', function (Blueprint $table) {
            $table->string("data_table")->default('larastart_data_structure_list');
        });

        foreach (self::$lists as $data_table) {
            Schema::create('larastart_data_structure_list_' . $data_table, function (Blueprint $table) {
                $table->unsignedSmallInteger('list_id');
                $table->unsignedInteger("key");
                $table->primary(["list_id", "key"]);
            });
        }
    }

    public function down()
    {
        Schema::table('larastart_data_structure_list_meta', function (Blueprint $table) {
            $table->dropColumn("data_table");
        });

        foreach (self::$lists as $data_table) {
            Schema::dropIfExists('larastart_data_structure_list_' . $data_table);
        }
    }
}
