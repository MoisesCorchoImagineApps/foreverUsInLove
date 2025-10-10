<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserPrivateChatTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_private_chat', function (Blueprint $table) {
            $table->integer('user_private_chat_id')->primary();
            $table->integer('request_from');
            $table->integer('request_to');
            $table->enum('request_status', ['requested', 'accepted', 'rejected'])->nullable();
            $table->string('invite_msg')->nullable();
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
        Schema::dropIfExists('user_private_chat');
    }
}
