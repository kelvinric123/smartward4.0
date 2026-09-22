<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Medication monitoring: a small formulary to pick from, the medication
     * orders a patient is on, and every dose recorded against an order.
     *
     * New tables only. Nothing existing is altered, and the feature stays
     * hidden until a user switches it on in Settings > Patient Details Tab.
     */
    public function up(): void
    {
        // Formulary: the list the "Add medication" form picks from
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('category', 60)->nullable();
            $table->decimal('default_dose', 10, 3)->nullable();
            $table->string('dose_unit', 20)->nullable();
            $table->string('default_route', 20)->nullable();
            $table->string('default_frequency', 20)->nullable();
            // Custom interval, or the minimum gap for a PRN order (e.g. morphine 4 h)
            $table->decimal('default_interval_hours', 5, 1)->nullable();
            $table->boolean('is_high_alert')->default(false);
            $table->string('caution')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One medication order for one patient
        Schema::create('patient_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('medication_id')->nullable()->constrained('medications')->nullOnDelete();

            // Copied from the formulary so later formulary edits never rewrite an order
            $table->string('medication_name', 120);
            $table->decimal('dose_amount', 10, 3);
            $table->string('dose_unit', 20);
            $table->string('route', 20);
            $table->string('frequency', 20);
            // Scheduled: time between doses. PRN: optional minimum gap. STAT: null.
            $table->unsignedInteger('interval_minutes')->nullable();
            $table->boolean('is_high_alert')->default(false);
            $table->string('instructions')->nullable();

            $table->string('status', 20)->default('active');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->timestamp('last_given_at')->nullable();

            $table->timestamp('stopped_at')->nullable();
            $table->foreignId('stopped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stop_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'status'], 'pm_patient_status_index');
            $table->index(['status', 'next_due_at'], 'pm_status_due_index');
        });

        // Every dose documented against an order: given, held or refused
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_medication_id')->constrained('patient_medications')->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamp('administered_at')->nullable();
            // What was due when this was recorded, so lateness can be shown later
            $table->timestamp('due_at')->nullable();
            $table->decimal('dose_amount', 10, 3)->nullable();
            $table->string('dose_unit', 20)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_medication_id', 'administered_at'], 'ma_order_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
        Schema::dropIfExists('patient_medications');
        Schema::dropIfExists('medications');
    }
};
