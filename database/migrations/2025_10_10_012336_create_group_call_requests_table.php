<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupCallRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_call_requests', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->bigInteger('sender_id');
            $table->bigInteger('room_id');
            $table->string('call_status');
            $table->string('members');
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
        Schema::dropIfExists('group_call_requests');
    }
}
