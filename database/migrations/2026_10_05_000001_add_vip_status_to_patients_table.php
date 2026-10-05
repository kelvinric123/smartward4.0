<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive only: nullable with no default (null = not a VIP), so existing rows,
     * ADT integration and other writers of the patients table are unaffected.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('vip_status', 20)->nullable(); // vip, vvip
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('vip_status');
        });
    }
};
