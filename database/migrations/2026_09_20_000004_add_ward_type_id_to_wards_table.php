<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable on purpose: every existing ward keeps working untouched, and a
     * ward type only starts mattering once someone binds one here.
     */
    public function up(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            $table->foreignId('ward_type_id')->nullable()->after('ward_name')
                ->constrained('ward_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ward_type_id');
        });
    }
};
