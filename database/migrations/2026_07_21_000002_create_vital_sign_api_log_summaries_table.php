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
        // Daily aggregates kept after raw vital_sign_api_logs rows are pruned.
        Schema::create('vital_sign_api_log_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date');
            $table->foreignId('api_user_id')->nullable()->constrained('api_users')->nullOnDelete();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('avg_response_time_ms')->nullable();
            $table->unsignedInteger('max_response_time_ms')->nullable();
            $table->timestamps();

            $table->unique(['summary_date', 'api_user_id', 'endpoint', 'method'], 'vs_api_log_summaries_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_sign_api_log_summaries');
    }
};
