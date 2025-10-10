<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserLikesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_likes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('like_from')->nullable();
            $table->integer('like_to')->nullable();
            $table->unsignedInteger('match_id')->default(0);
            $table->enum('match_status', ['nope', 'match', 'unmatch', ''])->nullable();
            $table->enum('like_status', ['nope', 'like', 'review', 'super_like'])->nullable();
            $table->enum('plan_status', ['free', 'paid'])->nullable();
            $table->string('notification', 25)->nullable();
            $table->enum('read_status', ['read', 'unread'])->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->enum('match_as', ['private_request', 'like'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_likes');
    }
}
