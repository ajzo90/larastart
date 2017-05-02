<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePriorityQueueTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('larastart_priority_queue', function (Blueprint $table) {
            $table->unsignedSmallInteger('queue_id')->index();
            $table->unsignedInteger("key")->index();
            $table->double("priority")->index()->default(0);
            $table->boolean("handled")->default(false);
            $table->unique(["queue_id", "key"]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('larastart_priority_queue');
    }
}
