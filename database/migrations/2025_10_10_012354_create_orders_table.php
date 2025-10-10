<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('user_id')->nullable()->index('user_id');
            $table->string('order_id', 191)->nullable()->unique('order_id');
            $table->string('payment_provider', 50)->nullable();
            $table->integer('subscription_id')->nullable();
            $table->string('currency_code', 50)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('payment_status', ['Paid', 'Pending'])->nullable();
            $table->enum('payment_type', ['play_store', 'appstore'])->nullable();
            $table->integer('month')->nullable();
            $table->enum('status', ['Active', 'Deactivate']);
            $table->integer('call_chat_time_limit')->default(0);
            $table->integer('like_per_day')->default(0);
            $table->string('plan_type')->nullable();
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
        Schema::dropIfExists('orders');
    }
}
