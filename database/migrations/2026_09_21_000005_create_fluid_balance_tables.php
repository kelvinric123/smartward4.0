<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The I/O chart (fluid balance) behind the I/O Chart tab of Patient Details.
     *
     * - fluid_balance_plans: how much the patient may take in over a chart day
     *   and the least urine expected per hour. Every change is a new row, so
     *   the latest row is the plan in force and the rest are its history.
     * - fluid_balance_entries: each measured intake or output. An entry made in
     *   error is struck out (voided) rather than deleted, like a paper chart.
     * - fluid_overload_assessments: bedside checks for fluid overload: pitting
     *   edema grade and sites, other signs, and weight.
     *
     * The times use useCurrent() so that MySQL never gives them an automatic
     * ON UPDATE, which would move the time when an entry is struck out.
     */
    public function up(): void
    {
        Schema::create('fluid_balance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->unsignedInteger('intake_limit_ml')->nullable();
            $table->unsignedSmallInteger('urine_min_ml_per_hour')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'created_at'], 'fbp_patient_created_index');
        });

        Schema::create('fluid_balance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->string('direction', 10);
            $table->string('category', 20);
            $table->unsignedInteger('volume_ml');
            $table->string('description', 120)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at'], 'fbe_patient_recorded_index');
        });

        Schema::create('fluid_overload_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->unsignedTinyInteger('edema_grade')->default(0);
            $table->json('edema_sites')->nullable();
            $table->json('signs')->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('assessed_at')->useCurrent();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'assessed_at'], 'foa_patient_assessed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fluid_overload_assessments');
        Schema::dropIfExists('fluid_balance_entries');
        Schema::dropIfExists('fluid_balance_plans');
    }
};
