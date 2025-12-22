<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ekad_response_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bed_id')->nullable()->constrained('beds')->onDelete('set null');
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('set null');
            $table->string('mac_address', 20)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->integer('response_code')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->string('triggered_by', 50)->default('observer'); // 'observer', 'manual', 'controller', etc.
            $table->timestamps();

            // Indexes for better query performance
            $table->index('bed_id');
            $table->index('patient_id');
            $table->index('success');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ekad_response_logs');
    }
};
