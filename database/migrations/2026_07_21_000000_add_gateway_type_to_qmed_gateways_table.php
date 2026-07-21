<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gateways now come in two kinds: the original vital-sign carts (MP5SC /
     * VS4 / CM100) and ECG gateways (Philips TC35 forwarders). The type drives
     * the dashboard badge/stats and lets each kind report its own telemetry.
     */
    public function up(): void
    {
        Schema::table('qmed_gateways', function (Blueprint $table) {
            $table->string('gateway_type', 20)->default('vital_sign')->after('gateway_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('qmed_gateways', function (Blueprint $table) {
            $table->dropColumn('gateway_type');
        });
    }
};
