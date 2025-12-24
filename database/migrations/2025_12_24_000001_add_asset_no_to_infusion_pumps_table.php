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
            $table->string('asset_no')->nullable()->after('device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('infusion_pumps', function (Blueprint $table) {
            $table->dropColumn('asset_no');
        });
    }
};
