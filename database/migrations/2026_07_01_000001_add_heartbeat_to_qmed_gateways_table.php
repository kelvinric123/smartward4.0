<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add heartbeat/telemetry fields so each cart (Raspberry Pi gateway) can be
     * identified by a stable gateway_id and monitored proactively (queue depth,
     * power stability, last-seen) instead of only noticing when vitals stop.
     */
    public function up(): void
    {
        Schema::table('qmed_gateways', function (Blueprint $table) {
            $table->string('gateway_id')->nullable()->unique()->after('name');
            // Hardware identity anchor: unique per Pi board, so an SD-card clone
            // deployed on new hardware cannot reuse another cart's identity.
            $table->string('cpu_serial')->nullable()->unique()->after('mac_address');
            $table->timestamp('last_heartbeat_at')->nullable()->after('last_ping_ip');
            $table->json('last_heartbeat')->nullable()->after('last_heartbeat_at');
            // healthy | warning | critical | offline
            $table->string('health_status')->nullable()->after('last_heartbeat');
        });
    }

    public function down(): void
    {
        Schema::table('qmed_gateways', function (Blueprint $table) {
            $table->dropUnique(['gateway_id']);
            $table->dropUnique(['cpu_serial']);
            $table->dropColumn([
                'gateway_id',
                'cpu_serial',
                'last_heartbeat_at',
                'last_heartbeat',
                'health_status',
            ]);
        });
    }
};
