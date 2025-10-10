<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room', function (Blueprint $table) {
            $table->integer('room_id')->primary();
            $table->string('room_name')->nullable();
            $table->string('channel_name')->nullable();
            $table->text('room_icon')->nullable();
            $table->text('room_icon1');
            $table->dateTime('date_from')->nullable();
            $table->dateTime('date_to')->nullable();
            $table->enum('status', ['Deactive', 'Active'])->default('Active');
            $table->boolean('call_request_status')->default(0);
            $table->integer('total_users')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('room');
    }
}
