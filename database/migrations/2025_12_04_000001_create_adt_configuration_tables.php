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
        // Main ADT Configuration table
        Schema::create('adt_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Default ADT Configuration');
            $table->string('listener_host')->default('0.0.0.0');
            $table->integer('listener_port')->default(3000);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_admit')->default(true);
            $table->boolean('auto_discharge')->default(true);
            $table->boolean('auto_transfer')->default(true);
            $table->json('settings')->nullable(); // Additional settings as JSON
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        // Hospital Mapping table - maps ADT hospital codes to local hospitals
        Schema::create('adt_hospital_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adt_configuration_id')->constrained()->onDelete('cascade');
            $table->string('adt_hospital_code'); // Code from HL7 message (sending_facility)
            $table->string('adt_hospital_name')->nullable();
            $table->foreignId('hospital_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['adt_configuration_id', 'adt_hospital_code'], 'adt_hospital_unique');
        });

        // Ward Mapping table - maps ADT ward/unit codes to local wards
        Schema::create('adt_ward_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adt_configuration_id')->constrained()->onDelete('cascade');
            $table->string('adt_ward_code'); // Ward/Unit code from PV1-3 (assigned_location)
            $table->string('adt_ward_name')->nullable();
            $table->foreignId('ward_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['adt_configuration_id', 'adt_ward_code'], 'adt_ward_unique');
        });

        // Bed Mapping table - maps ADT bed identifiers to local beds
        Schema::create('adt_bed_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adt_configuration_id')->constrained()->onDelete('cascade');
            $table->string('adt_bed_code'); // Bed identifier from PV1-3 (assigned_location component)
            $table->string('adt_bed_name')->nullable();
            $table->foreignId('bed_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['adt_configuration_id', 'adt_bed_code'], 'adt_bed_unique');
        });

        // ADT Message Logs table
        Schema::create('adt_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adt_configuration_id')->nullable()->constrained()->onDelete('set null');
            $table->string('message_type'); // ADT^A01, ADT^A03, etc.
            $table->string('event_type'); // A01, A02, A03, etc.
            $table->string('event_description')->nullable();
            $table->string('message_control_id')->nullable();
            $table->string('sending_application')->nullable();
            $table->string('sending_facility')->nullable();
            $table->string('patient_id')->nullable(); // From PID-3
            $table->string('patient_name')->nullable(); // From PID-5
            $table->string('patient_mrn')->nullable();
            $table->string('visit_number')->nullable();
            $table->string('assigned_location')->nullable(); // From PV1-3
            $table->string('source_ip')->nullable();
            $table->text('raw_message')->nullable();
            $table->json('parsed_data')->nullable();
            $table->enum('status', ['received', 'processed', 'failed', 'ignored'])->default('received');
            $table->text('error_message')->nullable();
            $table->json('action_taken')->nullable(); // What action was taken (admit/discharge/transfer)
            $table->foreignId('patient_id_ref')->nullable()->constrained('patients')->onDelete('set null');
            $table->foreignId('bed_id_ref')->nullable()->constrained('beds')->onDelete('set null');
            $table->timestamp('message_datetime')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->timestamps();
            
            $table->index(['event_type', 'created_at']);
            $table->index(['patient_mrn', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adt_message_logs');
        Schema::dropIfExists('adt_bed_mappings');
        Schema::dropIfExists('adt_ward_mappings');
        Schema::dropIfExists('adt_hospital_mappings');
        Schema::dropIfExists('adt_configurations');
    }
};









