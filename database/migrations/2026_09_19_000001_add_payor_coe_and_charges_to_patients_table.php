<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive only: every column is nullable with no default, so existing
     * rows, ADT integration and other writers of the patients table are unaffected.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Payor / insurance
            $table->string('payor_type', 50)->nullable(); // self_pay, insurance, corporate, government, other
            $table->string('payor_name')->nullable(); // Insurer / company / agency
            $table->string('payor_policy_number', 100)->nullable();
            $table->string('payor_gl_number', 100)->nullable(); // Guarantee Letter reference
            $table->decimal('payor_gl_amount', 12, 2)->nullable();
            $table->string('payor_status', 50)->nullable(); // pending, gl_requested, approved, partial, rejected, not_required
            $table->text('payor_remarks')->nullable();

            // Centre of Excellence programmes, e.g. ["CCPC Breast", "Chronic Kidney Disease"]
            $table->json('coe_indicators')->nullable();

            // Running bill summary
            $table->decimal('total_charges', 12, 2)->nullable();
            $table->decimal('deposit_paid', 12, 2)->nullable();
            $table->timestamp('charges_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'payor_type',
                'payor_name',
                'payor_policy_number',
                'payor_gl_number',
                'payor_gl_amount',
                'payor_status',
                'payor_remarks',
                'coe_indicators',
                'total_charges',
                'deposit_paid',
                'charges_updated_at',
            ]);
        });
    }
};
