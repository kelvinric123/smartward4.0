<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive only: nullable with no default, so existing readings, the monitor gateway
     * and the EWS scoring are unaffected.
     */
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            // How the patient is receiving oxygen, e.g. room_air, nasal_cannula, high_flow_mask
            $table->string('oxygen_delivery', 40)->nullable();
            // Litres per minute, where the device uses a flow rate
            $table->decimal('oxygen_flow_rate', 4, 1)->nullable();
            // Delivered oxygen concentration, where the device is set by percentage
            $table->unsignedTinyInteger('fio2_percent')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropColumn(['oxygen_delivery', 'oxygen_flow_rate', 'fio2_percent']);
        });
    }
};
