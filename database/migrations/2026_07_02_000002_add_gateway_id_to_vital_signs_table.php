<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record which gateway (cart) submitted a vital sign, so the vital-signs list
     * can show the source. Stored as the gateway_id string (unique on
     * qmed_gateways), which the VitalSign model relates to for the device name.
     */
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->string('gateway_id')->nullable()->index()->after('gateway_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropColumn('gateway_id');
        });
    }
};
