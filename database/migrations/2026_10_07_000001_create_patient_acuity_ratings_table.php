<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each time a nurse classifies a patient's care level (1 Minimal to 4 Intensive)
        // on the AI Nurse Schedule's Patient acuity tab. The patient's current level
        // stays on patients.nursing_level; these rows are the history, kept so a ward can
        // monitor its ratings over time and check them against the indicators.
        Schema::create('patient_acuity_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('bed_number', 50)->nullable();

            // The level the nurse gave, the level the indicators suggested at that
            // moment, and the level the patient had before (null when unclassified)
            $table->unsignedTinyInteger('level');
            $table->unsignedTinyInteger('suggested_level')->nullable();
            $table->unsignedTinyInteger('previous_level')->nullable();
            // The indicators behind the suggestion: [{dimension, label, level}]
            $table->json('reasons')->nullable();
            // Why the nurse's level differs from the suggestion, when it does
            $table->string('note', 255)->nullable();

            // The roster slot on duty when rated (the night shift belongs to the date it starts)
            $table->date('shift_date')->nullable();
            $table->string('shift', 8)->nullable();

            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rated_by_name')->nullable();
            $table->timestamp('rated_at');
            $table->timestamps();

            $table->index(['ward_id', 'rated_at']);
            $table->index(['patient_id', 'rated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_acuity_ratings');
    }
};
