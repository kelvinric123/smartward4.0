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
            // Vitals data mode for Patient Details tab: 'demo', 'real', 'off'
            $table->string('patient_vitals_mode', 20)->default('demo')->after('clinical_settings');
            // Vitals data mode for Bed Box display: 'demo', 'real', 'off'
            $table->string('bed_box_vitals_mode', 20)->default('demo')->after('patient_vitals_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ward_dashboard_settings', function (Blueprint $table) {
            $table->dropColumn(['patient_vitals_mode', 'bed_box_vitals_mode']);
        });
    }
};








