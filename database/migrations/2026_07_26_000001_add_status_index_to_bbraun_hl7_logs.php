<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The infusion-integration page counts and filters bbraun_hl7_logs by
     * status ('error'), which had no index - a full table scan on a table
     * that reaches millions of rows in production.
     */
    public function up(): void
    {
        Schema::table('bbraun_hl7_logs', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('bbraun_hl7_logs', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
