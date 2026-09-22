<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scores recorded against a patient for one of the assessment scales bound
     * to their ward's ward type.
     *
     * band_label is stored rather than derived on read: the library's bands can
     * be amended later, and a score already recorded should keep the reading it
     * was given at the time.
     *
     * item_scores holds the per-item breakdown when the scale was scored item
     * by item, and stays null when only a total was entered.
     */
    public function up(): void
    {
        Schema::create('clinical_indicator_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('clinical_indicator_id')->constrained('clinical_indicators')->cascadeOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->integer('score');
            $table->string('band_label')->nullable();
            $table->string('band_tone', 20)->nullable();
            $table->json('item_scores')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['patient_id', 'clinical_indicator_id', 'recorded_at'], 'cis_patient_indicator_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_indicator_scores');
    }
};
