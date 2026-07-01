<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the low-frequency "extended stats" heartbeat block (DB record
     * counts/date-range + network/Wi-Fi details) separately from the 30s live
     * heartbeat, so the details persist on the dashboard between the ~30-minute
     * extended sends.
     */
    public function up(): void
    {
        Schema::table('qmed_gateways', function (Blueprint $table) {
            $table->json('last_stats')->nullable()->after('last_heartbeat');
            $table->timestamp('last_stats_at')->nullable()->after('last_stats');
        });
    }

    public function down(): void
    {
        Schema::table('qmed_gateways', function (Blueprint $table) {
            $table->dropColumn(['last_stats', 'last_stats_at']);
        });
    }
};
