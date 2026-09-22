<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * hospital_id NULL means a system ward type seeded in code and offered to
     * every hospital; a value means a hospital admin added it for their own
     * hospital. Codes only have to be unique within one of those scopes, so the
     * global unique on code is replaced by a composite one.
     */
    public function up(): void
    {
        Schema::table('ward_types', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('id')
                ->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('clinical_indicator_id')->nullable()->after('name')
                ->constrained('clinical_indicators')->nullOnDelete();

            $table->dropUnique('ward_types_code_unique');
            $table->unique(['hospital_id', 'code'], 'ward_types_hospital_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ward_types', function (Blueprint $table) {
            $table->dropUnique('ward_types_hospital_code_unique');
            $table->dropConstrainedForeignId('clinical_indicator_id');
            $table->dropConstrainedForeignId('hospital_id');
            $table->unique('code', 'ward_types_code_unique');
        });
    }
};
