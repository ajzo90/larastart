<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePickerDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('picker_data', function (Blueprint $table) {
            $table->integer("picker_id")->index();
            $table->integer("user_id")->index();
            $table->double("rank");
            $table->integer("order")->nullable();
            $table->boolean("locked")->default(0);
            $table->boolean("picked")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('picker_data');
    }
}
