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
        Schema::table('ward_dashboard_settings', function (Blueprint $table) {
            $table->boolean('additional_info_read_only')->default(false)->after('bed_box_vitals_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ward_dashboard_settings', function (Blueprint $table) {
            $table->dropColumn('additional_info_read_only');
        });
    }
};
