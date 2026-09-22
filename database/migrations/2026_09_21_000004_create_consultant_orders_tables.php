<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders a consultant gives for a patient, carried out by the nurses on
     * the ward.
     *
     * Each open order belongs to one roster slot (a date and a shift code) and
     * to the nurse the roster puts on the patient's bed for that slot. Passing
     * it to the next shift moves both, and every pass is kept in
     * consultant_order_handovers, so it stays clear who held the order when.
     *
     * The consultant's name is copied onto the order, so the order still reads
     * correctly if the consultant record is later changed or removed.
     */
    public function up(): void
    {
        Schema::create('consultant_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('consultant_id')->nullable()->constrained('consultants')->nullOnDelete();
            $table->string('consultant_name')->nullable();

            $table->text('instruction');
            $table->string('urgency', 10)->default('routine');
            $table->timestamp('ordered_at');

            $table->foreignId('assigned_nurse_id')->nullable()->constrained('nurses')->nullOnDelete();
            $table->date('shift_date')->nullable();
            $table->string('shift_code', 4)->nullable();

            $table->string('status', 12)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('outcome_note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'status', 'ordered_at'], 'co_patient_status_time_index');
        });

        Schema::create('consultant_order_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultant_order_id')->constrained('consultant_orders')->cascadeOnDelete();
            $table->foreignId('from_nurse_id')->nullable()->constrained('nurses')->nullOnDelete();
            $table->date('from_shift_date')->nullable();
            $table->string('from_shift_code', 4)->nullable();
            $table->foreignId('to_nurse_id')->nullable()->constrained('nurses')->nullOnDelete();
            $table->date('to_shift_date');
            $table->string('to_shift_code', 4);
            $table->text('note')->nullable();
            $table->foreignId('handed_over_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_order_handovers');
        Schema::dropIfExists('consultant_orders');
    }
};
