<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create(User::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(User::COL_FIRST_NAME)->nullable();
            $table->string(User::COL_LAST_NAME)->nullable();
            $table->string(User::COL_EMAIL)->unique()->nullable();
            $table->string(User::COL_PHONE_NUMBER)->unique()->nullable();
            $table->boolean(User::COL_STATUS)->default(true);
            $table->boolean(User::COL_IS_ADMIN)->default(false);
            $table->string(User::COL_PASSWORD)->nullable();
            $table->timestamp(User::COL_EMAIL_VERIFIED_AT)->nullable();
            $table->timestamp(User::COL_PHONE_NUMBER_VERIFIED_AT)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
