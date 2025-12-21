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
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('hgt_enabled')->default(false)->after('isolation_type');
            $table->string('hgt_frequency')->nullable()->after('hgt_enabled'); // bd, tds, qid, pid
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['hgt_enabled', 'hgt_frequency']);
        });
    }
};
