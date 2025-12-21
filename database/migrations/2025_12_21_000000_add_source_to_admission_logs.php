<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admission_logs', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('booked_at');
            // 'manual' = created by user via dashboard
            // 'adt' = created via HL7 ADT message from HIS
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_logs', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
