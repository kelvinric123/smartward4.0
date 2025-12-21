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
        Schema::create('ward_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->string('bed_number');
            $table->string('type'); // 'ews', 'fall_risk', etc.
            $table->string('severity'); // 'normal', 'warning', 'urgent'
            $table->text('message');
            $table->integer('ews_score')->nullable();
            $table->enum('status', ['pending', 'responded'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Index for efficient queries
            $table->index(['ward_id', 'status']);
            $table->index(['patient_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ward_notifications');
    }
};
