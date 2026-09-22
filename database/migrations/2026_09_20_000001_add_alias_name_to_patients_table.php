<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullable with no default: existing rows and every other writer of the patients table are unaffected.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Also known as / preferred name (HL7 PID-9)
            $table->string('alias_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('alias_name');
        });
    }
};
