<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('nursing_level')->nullable()->default('none')->after('status');
            $table->string('diet_type')->nullable()->default('regular')->after('nursing_level');
            $table->string('fall_risk')->nullable()->default('none')->after('diet_type');
            $table->string('isolation_type')->nullable()->default('none')->after('fall_risk');
            $table->json('allergies')->nullable()->after('isolation_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['nursing_level', 'diet_type', 'fall_risk', 'isolation_type', 'allergies']);
        });
    }
};
