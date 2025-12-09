<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds pending_discharge_at timestamp for ADT A16 support
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Timestamp for when pending discharge was flagged (A16)
            $table->timestamp('pending_discharge_at')->nullable()->after('expected_discharge_at');
            
            // Discharged at timestamp for tracking actual discharge time (A03)
            $table->timestamp('discharged_at')->nullable()->after('pending_discharge_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['pending_discharge_at', 'discharged_at']);
        });
    }
};

