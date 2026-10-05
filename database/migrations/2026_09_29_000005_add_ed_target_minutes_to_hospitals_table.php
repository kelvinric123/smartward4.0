<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The time-in-ED target Command Center V2 (ED) measures against: patients
     * in the ED longer than this since they arrived are flagged. Set from the
     * board's settings; two hours to start with.
     */
    public function up(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->unsignedSmallInteger('ed_target_minutes')->default(120)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropColumn('ed_target_minutes');
        });
    }
};
