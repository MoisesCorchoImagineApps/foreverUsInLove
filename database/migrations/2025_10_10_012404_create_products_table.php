<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->integer('product_id')->primary();
            $table->string('product_name')->nullable();
            $table->string('price', 22)->default('0');
            $table->integer('qty');
            $table->enum('type', ['super_like'])->nullable();
            $table->string('apple_product_id', 191)->nullable()->unique('apple_product_id');
            $table->string('google_product_id', 191)->nullable()->unique('google_product_id');
            $table->enum('status', ['active', 'deactive'])->default('active');
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
        Schema::dropIfExists('products');
    }
}
