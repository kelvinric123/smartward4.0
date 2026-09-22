<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One transfusion episode: a single unit given to a patient, from the
     * pre-start bedside check through to completion.
     *
     * The four pre-start checks are stored as their own columns rather than a
     * blob so that an incomplete check is queryable, and both blood groups are
     * captured on the episode because the bedside check records what was on the
     * unit and the patient at that moment, not what a record says today.
     */
    public function up(): void
    {
        Schema::create('blood_transfusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();

            $table->string('unit_number', 64);
            $table->string('product_type', 64);
            $table->string('unit_blood_group', 8)->nullable();
            $table->string('patient_blood_group', 8)->nullable();
            $table->string('crossmatch_reference', 64)->nullable();
            $table->timestamp('unit_expires_at')->nullable();
            $table->unsignedInteger('volume_ml')->nullable();
            $table->unsignedInteger('prescribed_minutes')->nullable();

            // Pre-start bedside checks
            $table->boolean('check_crossmatch')->default(false);
            $table->boolean('check_product')->default(false);
            $table->boolean('check_expiry')->default(false);
            $table->boolean('check_identity')->default(false);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();

            $table->string('status', 20)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('stop_reason')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'status'], 'bt_patient_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blood_transfusions');
    }
};
