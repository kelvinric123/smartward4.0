<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C+ is the main source of a patient's isolation: the Isolation box on the
 * C+ admission card, which rpa_cplus_smartward reads. C+ records only that
 * the patient is isolated, not the kind, so a ticked box shows as the
 * "Isolation" type unless staff have picked a kind in Patient Details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // The Isolation box as the RPA last read it; null until it has been read
            $table->boolean('cplus_isolation')->nullable()->after('isolation_type');
        });

        DB::table('isolation_types')->insertOrIgnore([
            'code' => 'ISO',
            'name' => 'Isolation',
            'description' => 'Ticked on the C+ admission card, which does not record the kind of isolation',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('cplus_isolation');
        });
    }
};
