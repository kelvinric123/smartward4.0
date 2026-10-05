<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API tokens for Integration Users (e.g. the rpa_cplus_smartward RPA). Only a
 * SHA-256 hash is kept; the token itself is shown once when it is generated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 64)->nullable()->unique()->after('remember_token');
            $table->timestamp('api_token_created_at')->nullable()->after('api_token');
            $table->timestamp('api_token_last_used_at')->nullable()->after('api_token_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_token']);
            $table->dropColumn(['api_token', 'api_token_created_at', 'api_token_last_used_at']);
        });
    }
};
