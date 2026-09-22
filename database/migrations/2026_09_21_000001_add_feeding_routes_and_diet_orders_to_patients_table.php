<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive only: nullable with no default. Used when the ward manages diet directly
     * (Settings > Patient Additional Info); nothing writes them while diet comes from ADT.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Non-oral feeding in use, e.g. ["ngt", "tpn"]
            $table->json('feeding_routes')->nullable();
            // Free-text diet instructions, e.g. "Clear fluids until 6pm, NBM from midnight for OT"
            $table->text('diet_orders')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['feeding_routes', 'diet_orders']);
        });
    }
};
