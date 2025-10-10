<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name');
            $table->string('device_key', 250)->nullable();
            $table->string('thumbnail_image')->nullable();
            $table->string('dob')->nullable();
            $table->integer('age')->nullable();
            $table->string('email')->nullable()->unique('users_email_unique');
            $table->enum('status', ['active', 'deactivate'])->default('active');
            $table->string('phone')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'transexual', 'transgender', 'non-binary'])->nullable();
            $table->string('job_title')->nullable();
            $table->string('login_otp')->nullable();
            $table->text('google_id')->nullable();
            $table->text('fb_id')->nullable();
            $table->text('apple_id')->nullable();
            $table->string('login_type')->nullable();
            $table->string('otp_expird_time')->nullable();
            $table->string('address')->nullable();
            $table->text('latitude')->nullable();
            $table->text('longitude')->nullable();
            $table->text('image1');
            $table->text('image2');
            $table->text('image3');
            $table->text('image4');
            $table->text('image5');
            $table->text('image6');
            $table->text('profile_video')->nullable();
            $table->double('lastseen')->nullable();
            $table->text('fcm_token')->nullable();
            $table->text('api_token')->nullable();
            $table->string('device_type');
            $table->text('about')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->integer('email_verified')->default(0);
            $table->integer('email_verified_otp')->nullable();
            $table->text('password')->nullable();
            $table->integer('coins');
            $table->rememberToken();
            $table->string('available_video_call_duration')->default('0');
            $table->string('user_type')->default('user');
            $table->string('height')->nullable();
            $table->enum('user_intrested_in', ['male', 'female', 'both', 'other', 'transexual', 'transgender', 'non-binary']);
            $table->integer('covid_vaccine')->nullable();
            $table->integer('drink')->nullable();
            $table->integer('drugs')->nullable();
            $table->integer('first_date_ice_breaker')->nullable();
            $table->integer('horoscope')->nullable();
            $table->integer('life_style')->nullable();
            $table->integer('political_leaning')->nullable();
            $table->integer('relationship_status')->nullable();
            $table->integer('religion')->nullable();
            $table->integer('smoking')->nullable();
            $table->string('hobbies')->nullable();
            $table->boolean('is_plan_expired_notified')->default(0);
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
        Schema::dropIfExists('users');
    }
}
