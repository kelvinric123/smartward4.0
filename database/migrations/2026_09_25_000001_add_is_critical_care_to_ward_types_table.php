<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wards whose ward type is marked critical care are the ones listed on the
     * Critical Care Ward Dashboard. The ICU and HDU system types start out
     * marked; admins change any type from the Ward Types page.
     */
    public function up(): void
    {
        Schema::table('ward_types', function (Blueprint $table) {
            $table->boolean('is_critical_care')->default(false)->after('description');
        });

        DB::table('ward_types')
            ->whereNull('hospital_id')
            ->whereIn('code', ['ICU', 'HDU'])
            ->update(['is_critical_care' => true]);
    }

    public function down(): void
    {
        Schema::table('ward_types', function (Blueprint $table) {
            $table->dropColumn('is_critical_care');
        });
    }
};
