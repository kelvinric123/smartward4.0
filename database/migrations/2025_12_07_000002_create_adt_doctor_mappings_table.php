<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates doctor mapping table for ADT integration
     * Maps HIS doctor codes to SmartWard consultants
     */
    public function up(): void
    {
        Schema::create('adt_doctor_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adt_configuration_id')->constrained()->onDelete('cascade');
            $table->string('adt_doctor_code'); // Doctor code from HIS (e.g., DOCTOR0, DOCTOR1)
            $table->string('adt_doctor_name')->nullable(); // Name from HIS for reference
            $table->string('doctor_type')->default('attending'); // attending, referring, consulting, admitting
            $table->foreignId('consultant_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Unique constraint per configuration and doctor code/type
            $table->unique(['adt_configuration_id', 'adt_doctor_code', 'doctor_type'], 'adt_doctor_unique');
            
            // Index for lookups
            $table->index(['adt_doctor_code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adt_doctor_mappings');
    }
};
























