<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wards whose ward type is marked emergency (the ED's zones) are the ones
     * on Command Center V2 (ED), and are left out of the hospital-wide
     * Command Center V2. The ED Observation Bay system type starts out marked;
     * admins change any type from the Ward Types page.
     */
    public function up(): void
    {
        Schema::table('ward_types', function (Blueprint $table) {
            $table->boolean('is_emergency')->default(false)->after('is_critical_care');
        });

        DB::table('ward_types')
            ->whereNull('hospital_id')
            ->where('code', 'EDOB')
            ->update(['is_emergency' => true]);
    }

    public function down(): void
    {
        Schema::table('ward_types', function (Blueprint $table) {
            $table->dropColumn('is_emergency');
        });
    }
};
