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
        // Infusion API Users (similar to vital sign API users)
        Schema::create('infusion_api_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('api_token', 100)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('request_count')->default(0);
            $table->timestamps();
        });

        // Infusion Pumps (devices sending data)
        Schema::create('infusion_pumps', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique();
            $table->string('device_name')->nullable();
            $table->string('device_type')->nullable(); // syringe_pump, volumetric_pump, etc.
            $table->string('location')->nullable();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // Infusions (active/completed infusion sessions)
        Schema::create('infusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('infusion_pump_id')->nullable()->constrained('infusion_pumps')->nullOnDelete();
            $table->string('medication_name');
            $table->string('medication_code')->nullable(); // HL7 medication code
            $table->decimal('total_volume', 10, 2)->nullable(); // ml
            $table->decimal('infused_volume', 10, 2)->default(0); // ml
            $table->decimal('remaining_volume', 10, 2)->nullable(); // ml
            $table->decimal('flow_rate', 10, 2)->nullable(); // ml/hr
            $table->decimal('dose_rate', 10, 4)->nullable(); // units depend on medication
            $table->string('dose_unit')->nullable(); // mg/hr, mcg/kg/min, etc.
            $table->integer('duration_minutes')->nullable(); // total planned duration
            $table->integer('elapsed_minutes')->default(0);
            $table->integer('remaining_minutes')->nullable();
            $table->enum('status', ['pending', 'running', 'paused', 'completed', 'stopped', 'alarming'])->default('pending');
            $table->string('alarm_type')->nullable(); // occlusion, air_in_line, empty, low_battery, etc.
            $table->text('alarm_message')->nullable();
            $table->boolean('is_warning')->default(false); // true if infusion is about to finish (e.g., <15 min remaining)
            $table->integer('warning_threshold_minutes')->default(15);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['status', 'is_warning']);
        });

        // Infusion API Logs
        Schema::create('infusion_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('infusion_api_user_id')->nullable()->constrained('infusion_api_users')->nullOnDelete();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->string('hl7_message_type')->nullable(); // ORU, ORM, etc.
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('status_code');
            $table->string('ip_address', 45)->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infusion_api_logs');
        Schema::dropIfExists('infusions');
        Schema::dropIfExists('infusion_pumps');
        Schema::dropIfExists('infusion_api_users');
    }
};

