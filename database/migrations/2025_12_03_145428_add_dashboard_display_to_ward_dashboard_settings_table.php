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
        Schema::table('ward_dashboard_settings', function (Blueprint $table) {
            $table->json('dashboard_display')->nullable()->after('clinical_indicator_options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ward_dashboard_settings', function (Blueprint $table) {
            $table->dropColumn('dashboard_display');
        });
    }
};
