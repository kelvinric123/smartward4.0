<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nursing care plan: a patient's nursing diagnoses (problems), each with a
 * goal and the interventions to reach it, and an evaluation per shift of how
 * far the goal was met. New tables only; nothing existing changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_care_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            // Library template it was started from (null for one written by hand)
            $table->string('template_key', 40)->nullable();
            $table->string('category', 40)->nullable();
            $table->string('diagnosis');
            $table->text('related_to')->nullable();
            $table->text('goal');
            $table->json('interventions')->nullable();
            $table->string('status', 20)->default('active'); // active | resolved | discontinued
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolve_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
        });

        Schema::create('nursing_care_plan_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nursing_care_plan_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('outcome', 20); // met | partly_met | not_met
            $table->text('note')->nullable();
            // The roster slot it was evaluated in, so "evaluated this shift" is exact
            $table->date('shift_date')->nullable();
            $table->string('shift_code', 4)->nullable();
            $table->timestamp('evaluated_at');
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_care_plan_evaluations');
        Schema::dropIfExists('nursing_care_plan_items');
    }
};
