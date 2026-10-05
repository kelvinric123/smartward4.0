<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * On a discharge entry, the ward the patient went on to, when the stay
     * ended in a stay elsewhere rather than going home: an ED visit that ended
     * in an admission to a ward. Command Center V2 (ED) splits the ED's
     * departures by it. Null for everything else.
     */
    public function up(): void
    {
        Schema::table('admission_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('to_ward_id')->nullable()->after('ward_id');
            $table->index('to_ward_id');
        });
    }

    public function down(): void
    {
        Schema::table('admission_logs', function (Blueprint $table) {
            $table->dropIndex(['to_ward_id']);
            $table->dropColumn('to_ward_id');
        });
    }
};
