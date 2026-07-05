<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the server-assigned hostname and the SSH connection details so the
     * dashboard can offer a ready-to-paste SSH command and a reachability ping.
     */
    public function up(): void
    {
        Schema::table('qmed_gateways', function (Blueprint $table) {
            $table->string('hostname')->nullable()->after('gateway_id');
            $table->string('ssh_user')->nullable()->after('last_ping_ip');
            $table->unsignedSmallInteger('ssh_port')->nullable()->after('ssh_user');
        });
    }

    public function down(): void
    {
        Schema::table('qmed_gateways', function (Blueprint $table) {
            $table->dropColumn(['hostname', 'ssh_user', 'ssh_port']);
        });
    }
};
