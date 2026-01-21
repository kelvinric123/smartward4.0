<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vital_sign_api_logs', function (Blueprint $table) {
            $table->json('debug_data')->nullable()->after('response_time_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vital_sign_api_logs', function (Blueprint $table) {
            $table->dropColumn('debug_data');
        });
    }
};
