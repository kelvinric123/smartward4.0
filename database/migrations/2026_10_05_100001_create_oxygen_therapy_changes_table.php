<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Oxygen Therapy tab of Patient Details: every change to a patient's oxygen
     * (device, flow rate and/or FiO2, SpO2 target), from started_at until the next
     * change. Changing to room air is a change like any other. The oxygen columns
     * match vital_signs, which records oxygen with each reading, so both read alike.
     * A change made in error is struck out (voided) rather than deleted.
     *
     * The times use useCurrent() so that MySQL never gives them an automatic
     * ON UPDATE, which would move the time when a change is struck out.
     */
    public function up(): void
    {
        Schema::create('oxygen_therapy_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->string('oxygen_delivery', 40);
            $table->decimal('oxygen_flow_rate', 4, 1)->nullable();
            $table->unsignedTinyInteger('fio2_percent')->nullable();
            $table->unsignedTinyInteger('target_spo2_min')->nullable();
            $table->unsignedTinyInteger('target_spo2_max')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'started_at'], 'otc_patient_started_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oxygen_therapy_changes');
    }
};
