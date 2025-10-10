<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plan', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('search_filters');
            $table->integer('like_per_day')->default(20);
            $table->integer('super_like_par_day');
            $table->string('group_video_call_and_chat');
            $table->string('video_call_duration');
            $table->string('my_likes')->nullable();
            $table->string('who_views_me');
            $table->string('private_chat_request')->default('20');
            $table->float('price')->default(0);
            $table->string('currency_code')->nullable();
            $table->integer('month')->default(1);
            $table->integer('plan_duration')->default(0);
            $table->string('plan_type');
            $table->string('google_plan_id');
            $table->string('apple_plan_id');
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
        Schema::dropIfExists('plan');
    }
}
