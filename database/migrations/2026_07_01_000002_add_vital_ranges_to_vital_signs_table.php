<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the SpO2 and pulse-rate RANGE observed during a capture window, in
     * addition to the single representative value. Lets the dashboard show e.g.
     * "SpO2 95-96" / "PR 80-85" while existing single-value consumers (charts,
     * EWS scoring) keep working off spo2 / pulse_rate.
     */
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->integer('spo2_min')->nullable()->after('spo2');
            $table->integer('spo2_max')->nullable()->after('spo2_min');
            $table->integer('pulse_rate_min')->nullable()->after('pulse_rate');
            $table->integer('pulse_rate_max')->nullable()->after('pulse_rate_min');
        });
    }

    public function down(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropColumn(['spo2_min', 'spo2_max', 'pulse_rate_min', 'pulse_rate_max']);
        });
    }
};
