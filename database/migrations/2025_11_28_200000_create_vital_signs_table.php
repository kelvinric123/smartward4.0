<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->string('admission_id')->nullable()->index(); // Unique ID per admission (e.g., "ADM-{patient_id}-{timestamp}")
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Key vital signs
            $table->integer('systolic_bp')->nullable(); // SBP mmHg
            $table->integer('diastolic_bp')->nullable(); // DBP mmHg
            $table->integer('pulse_rate')->nullable(); // bpm
            $table->decimal('temperature', 4, 1)->nullable(); // Celsius
            $table->integer('spo2')->nullable(); // SpO2 percentage
            $table->integer('respiratory_rate')->nullable(); // breaths per minute
            
            // Reading type
            $table->enum('reading_type', ['single', 'full'])->default('single');
            
            // Additional fields
            $table->string('notes')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
            
            // Indexes for search optimization
            $table->index(['patient_id', 'admission_id']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
    }
};




































