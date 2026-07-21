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
        Schema::table('vital_sign_api_logs', function (Blueprint $table) {
            $table->index('created_at', 'vs_api_logs_created_at_index');
            $table->index(['api_user_id', 'created_at'], 'vs_api_logs_user_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vital_sign_api_logs', function (Blueprint $table) {
            $table->dropIndex('vs_api_logs_created_at_index');
            $table->dropIndex('vs_api_logs_user_created_index');
        });
    }
};
