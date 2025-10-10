<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_order', function (Blueprint $table) {
            $table->integer('product_order_id')->primary();
            $table->integer('product_id');
            $table->integer('user_id');
            $table->enum('payment_status', ['Paid', 'Unpaid'])->nullable();
            $table->integer('qty');
            $table->string('status')->nullable();
            $table->string('payment_type');
            $table->string('order_id', 191)->nullable()->unique('order_id');
            $table->string('payment_provider', 50)->nullable();
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
        Schema::dropIfExists('product_order');
    }
}
