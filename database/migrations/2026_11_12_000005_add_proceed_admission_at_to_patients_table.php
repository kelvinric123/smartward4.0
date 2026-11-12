<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proceed for admission: when the ED decided to admit a patient to a ward.
 * Until they move to the ward they wait in the ED for a bed; Command Center
 * V2 (ED)'s ED flow today counts both, the decisions and those waiting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('proceed_admission_at')->nullable()->after('pending_discharge_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['proceed_admission_at']);
            $table->dropColumn('proceed_admission_at');
        });
    }
};
