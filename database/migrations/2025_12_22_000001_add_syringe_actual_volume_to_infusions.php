<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Adds syringe_actual_volume field to infusions table for B.Braun pump data
     */
    public function up(): void
    {
        Schema::table('infusions', function (Blueprint $table) {
            if (!Schema::hasColumn('infusions', 'syringe_actual_volume')) {
                $table->decimal('syringe_actual_volume', 10, 2)->nullable()->after('syringe_size');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('infusions', function (Blueprint $table) {
            if (Schema::hasColumn('infusions', 'syringe_actual_volume')) {
                $table->dropColumn('syringe_actual_volume');
            }
        });
    }
};
