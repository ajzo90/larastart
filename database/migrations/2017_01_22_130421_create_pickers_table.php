<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePickersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pickers', function (Blueprint $table) {
            $table->increments("id");
            $table->string('namespace')->index();
            $table->string('key')->index();
            $table->string('hash')->nullable();
            $table->integer('min')->nullable();
            $table->integer('max')->nullable();
            $table->boolean('picked')->default(0);
            $table->boolean('locked')->default(0);
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('updated_users_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('registered_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pickers');
    }
}
