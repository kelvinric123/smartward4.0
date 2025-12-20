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
        if (!Schema::hasTable('bbraun_hl7_logs')) {
            Schema::create('bbraun_hl7_logs', function (Blueprint $table) {
                $table->id();
                $table->string('message_control_id')->nullable();
                $table->string('message_type')->nullable(); // ORU, ORM, ADT, RAS, RDE, RGV
                $table->string('event_type')->nullable(); // R01, O01, etc.
                $table->string('sending_application')->nullable();
                $table->string('sending_facility')->nullable();
                $table->string('patient_mrn')->nullable();
                $table->string('patient_name')->nullable();
                $table->string('device_id')->nullable();
                $table->string('medication_name')->nullable();
                $table->decimal('flow_rate', 10, 2)->nullable(); // ml/hr
                $table->decimal('total_volume', 10, 2)->nullable(); // ml
                $table->decimal('infused_volume', 10, 2)->nullable(); // ml
                $table->decimal('remaining_volume', 10, 2)->nullable(); // ml
                $table->string('pump_status')->nullable(); // running, paused, stopped, completed, alarming
                $table->string('alarm_type')->nullable();
                $table->text('alarm_message')->nullable();
                $table->longText('raw_message')->nullable(); // Raw HL7 message
                $table->json('parsed_data')->nullable(); // Full parsed data as JSON
                $table->string('source_ip', 45)->nullable(); // Source IP address
                $table->string('status')->default('received'); // received, processed, error
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index('message_type');
                $table->index('patient_mrn');
                $table->index('device_id');
                $table->index('pump_status');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bbraun_hl7_logs');
    }
};

