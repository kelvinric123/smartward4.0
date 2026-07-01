<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Associate a gateway (cart) with a ward, chosen during setup.sh. Lets the
     * dashboard group carts by ward and route heartbeat alerts to the right unit.
     */
    public function up(): void
    {
        Schema::table('qmed_gateways', function (Blueprint $table) {
            $table->foreignId('ward_id')->nullable()->after('location')
                ->constrained('wards')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('qmed_gateways', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ward_id');
        });
    }
};
