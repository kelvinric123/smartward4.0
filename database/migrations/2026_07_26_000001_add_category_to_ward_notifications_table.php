<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Patient-raised smart calls need to record which category the patient
     * picked (pain, water, bathroom, ...) so the ward dashboard can show it
     * and the patient app can render the matching icon in its history.
     */
    public function up(): void
    {
        Schema::table('ward_notifications', function (Blueprint $table) {
            $table->string('category')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('ward_notifications', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
