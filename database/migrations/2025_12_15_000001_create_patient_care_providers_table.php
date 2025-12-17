<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores care providers from ADT PV1 segment:
     * - PV1-7: Attending Doctor (role = 'attending')
     * - PV1-8: Referring Doctor (role = 'referring')
     * - PV1-9: Consulting Doctor (role = 'consulting')
     */
    public function up(): void
    {
        Schema::create('patient_care_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            
            // Role from ADT PV1: attending (PV1-7), referring (PV1-8), consulting (PV1-9)
            $table->string('role'); // attending, referring, consulting
            
            // Doctor code from ADT (e.g., "DKAMJIT")
            $table->string('doctor_code');
            
            // Doctor name parsed from ADT (e.g., "KAMALJIT KAUR D/O HARBAN SINGH")
            $table->string('doctor_name')->nullable();
            
            // Optional link to consultants table if matched
            $table->foreignId('consultant_id')->nullable()->constrained()->onDelete('set null');
            
            // Optional link to anaesthetists table if matched
            $table->foreignId('anaesthetist_id')->nullable()->constrained()->onDelete('set null');
            
            // Source: 'adt' for ADT messages, 'manual' for manual entry
            $table->string('source')->default('adt');
            
            // Visit number for tracking across admissions
            $table->string('visit_number')->nullable();
            
            // When this provider was assigned (from ADT message datetime)
            $table->timestamp('assigned_at')->nullable();
            
            // Is this provider currently active for this patient
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Index for faster lookups
            $table->index(['patient_id', 'role', 'is_active']);
            $table->index(['doctor_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_care_providers');
    }
};



