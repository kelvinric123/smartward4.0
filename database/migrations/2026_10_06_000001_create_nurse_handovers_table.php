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
        Schema::create('nurse_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('ward_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('bed_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('from_nurse_id')->constrained('nurses')->onDelete('cascade');
            // Null = open handover: any nurse taking over the patient can receive it
            $table->foreignId('to_nurse_id')->nullable()->constrained('nurses')->onDelete('set null');
            $table->string('from_shift', 10)->nullable();
            $table->string('to_shift', 10)->nullable();
            $table->string('condition_status', 20)->nullable(); // stable, improving, deteriorating, critical
            $table->text('patient_condition')->nullable();
            $table->text('nursing_plan')->nullable();
            $table->unsignedTinyInteger('ews')->nullable(); // EWS snapshot at handover time
            $table->string('status', 20)->default('pending'); // pending, received
            $table->foreignId('received_by_nurse_id')->nullable()->constrained('nurses')->onDelete('set null');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
            $table->index(['to_nurse_id', 'status']);
            $table->index(['from_nurse_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nurse_handovers');
    }
};
