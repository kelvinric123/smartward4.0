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
        Schema::table('infusion_pumps', function (Blueprint $table) {
            if (!Schema::hasColumn('infusion_pumps', 'serial_no')) {
                $table->string('serial_no')->nullable()->after('asset_no');
            }
        });

        Schema::table('bbraun_hl7_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('bbraun_hl7_logs', 'serial_no')) {
                $table->string('serial_no')->nullable()->after('device_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('infusion_pumps', function (Blueprint $table) {
            if (Schema::hasColumn('infusion_pumps', 'serial_no')) {
                $table->dropColumn('serial_no');
            }
        });

        Schema::table('bbraun_hl7_logs', function (Blueprint $table) {
            if (Schema::hasColumn('bbraun_hl7_logs', 'serial_no')) {
                $table->dropColumn('serial_no');
            }
        });
    }
};
