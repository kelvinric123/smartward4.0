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
            $table->json('clinical_indicator_options')->nullable()->after('patient_info_display');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ward_dashboard_settings', function (Blueprint $table) {
            $table->dropColumn('clinical_indicator_options');
        });
    }
};
