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
        Schema::table('nurses', function (Blueprint $table) {
            $table->boolean('is_tagging')->default(false)->after('is_active');
            $table->foreignId('ward_id')->nullable()->after('is_tagging')->constrained('wards')->nullOnDelete();
            $table->foreignId('tagged_nurse_id')->nullable()->after('ward_id')->constrained('nurses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nurses', function (Blueprint $table) {
            $table->dropForeign(['tagged_nurse_id']);
            $table->dropForeign(['ward_id']);
            $table->dropColumn(['tagged_nurse_id', 'ward_id', 'is_tagging']);
        });
    }
};
