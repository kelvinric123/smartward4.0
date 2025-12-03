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
        Schema::create('patient_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('ward_id')->nullable()->constrained()->onDelete('set null');
            $table->string('bed_number')->nullable();
            $table->string('location'); // e.g. Radiology, Surgery, Cath Lab, etc
            $table->string('location_type')->nullable(); // quick type like radiology, surgery, other
            $table->text('notes')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, sent, returned, cancelled
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_movements');
    }
};


