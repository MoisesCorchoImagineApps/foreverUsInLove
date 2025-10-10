<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOnGoingGroupCallTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('on_going_group_call', function (Blueprint $table) {
            $table->integer('on_going_group_call_id')->primary();
            $table->integer('room_id')->nullable();
            $table->integer('user_id');
            $table->string('channel_name')->nullable();
            $table->string('u_id')->nullable();
            $table->string('remote_id');
            $table->string('token')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('on_going_group_call');
    }
}
