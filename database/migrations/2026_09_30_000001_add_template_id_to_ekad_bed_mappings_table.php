<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The SEEKINK template a screen is painted with. Screens of another size
     * (or another ward's layout) need their own template; null keeps the
     * screen on the Template ID set under Configuration & Login.
     */
    public function up(): void
    {
        Schema::table('ekad_bed_mappings', function (Blueprint $table) {
            $table->string('template_id', 64)->nullable()->after('mac_address');
        });
    }

    public function down(): void
    {
        Schema::table('ekad_bed_mappings', function (Blueprint $table) {
            $table->dropColumn('template_id');
        });
    }
};
