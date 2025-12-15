<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('api_token', 80)->unique()->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->integer('request_count')->default(0);
            $table->timestamps();
        });

        // Create a table to log vital sign API requests
        Schema::create('vital_sign_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_user_id')->constrained()->onDelete('cascade');
            $table->string('endpoint');
            $table->string('method');
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('status_code');
            $table->string('ip_address')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_sign_api_logs');
        Schema::dropIfExists('api_users');
    }
};


























