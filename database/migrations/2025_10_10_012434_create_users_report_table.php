<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users_report', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index('users_report_user_id_foreign');
            $table->unsignedInteger('reporter_id')->index('users_report_reporter_id_foreign');
            $table->unsignedInteger('report_reason')->index('users_report_report_reason_foreign');
            $table->text('message');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->enum('type', ['Report', 'Unmatch'])->nullable();
            $table->integer('action')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users_report');
    }
}
