<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOnGoingCallTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('on_going_call', function (Blueprint $table) {
            $table->integer('on_going_call_id')->primary();
            $table->integer('sender_user_id');
            $table->integer('reaciver_user_id');
            $table->string('channel_name');
            $table->string('sender_u_id');
            $table->integer('reaciver_u_id');
            $table->text('sender_token');
            $table->text('reaciver_token');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->string('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('on_going_call');
    }
}
