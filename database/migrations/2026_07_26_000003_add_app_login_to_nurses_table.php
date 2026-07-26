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
        Schema::table('nurses', function (Blueprint $table) {
            // Nurse mobile app login credentials (configured in Nurse edit page)
            $table->string('app_username')->nullable()->unique()->after('registration_number');
            $table->string('app_password')->nullable()->after('app_username');
            $table->string('app_api_token', 64)->nullable()->index()->after('app_password');
            $table->timestamp('app_token_expires_at')->nullable()->after('app_api_token');
            $table->timestamp('app_last_login_at')->nullable()->after('app_token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nurses', function (Blueprint $table) {
            $table->dropColumn([
                'app_username',
                'app_password',
                'app_api_token',
                'app_token_expires_at',
                'app_last_login_at',
            ]);
        });
    }
};
