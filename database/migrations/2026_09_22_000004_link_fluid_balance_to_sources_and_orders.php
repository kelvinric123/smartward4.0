<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets the I/O chart fill itself from the rest of the ward
     * (App\Services\FluidBalanceLinks):
     *
     * - fluid_balance_entries.source / source_id: an entry added
     *   automatically points at what it came from (a blood unit, a dose, a
     *   pump infusion). source_reading keeps the pump's infused-volume
     *   counter at the time, so the next entry adds only what ran since.
     * - consultant_orders.fluid_limit_ml / urine_min_ml_per_hour: an order
     *   can carry a fluid restriction, which becomes the I/O fluid plan;
     *   fluid_balance_plans.consultant_order_id records which order set it.
     * - patient_medications.infusion_volume_ml: the volume each dose of an IV
     *   medication is given in (e.g. 100 mL), charted as IV intake.
     */
    public function up(): void
    {
        Schema::table('fluid_balance_entries', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('description');
            $table->unsignedBigInteger('source_id')->nullable()->after('source');
            $table->decimal('source_reading', 10, 2)->nullable()->after('source_id');

            $table->index(['source', 'source_id'], 'fbe_source_index');
        });

        Schema::table('consultant_orders', function (Blueprint $table) {
            $table->unsignedInteger('fluid_limit_ml')->nullable()->after('instruction');
            $table->unsignedSmallInteger('urine_min_ml_per_hour')->nullable()->after('fluid_limit_ml');
        });

        Schema::table('fluid_balance_plans', function (Blueprint $table) {
            $table->foreignId('consultant_order_id')->nullable()->after('notes')
                ->constrained('consultant_orders')->nullOnDelete();
        });

        Schema::table('patient_medications', function (Blueprint $table) {
            $table->unsignedInteger('infusion_volume_ml')->nullable()->after('dose_unit');
        });
    }

    public function down(): void
    {
        Schema::table('patient_medications', function (Blueprint $table) {
            $table->dropColumn('infusion_volume_ml');
        });

        Schema::table('fluid_balance_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consultant_order_id');
        });

        Schema::table('consultant_orders', function (Blueprint $table) {
            $table->dropColumn(['fluid_limit_ml', 'urine_min_ml_per_hour']);
        });

        Schema::table('fluid_balance_entries', function (Blueprint $table) {
            $table->dropIndex('fbe_source_index');
            $table->dropColumn(['source', 'source_id', 'source_reading']);
        });
    }
};
