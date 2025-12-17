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
        Schema::create('ward_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->onDelete('cascade');
            $table->foreignId('bed_id')->constrained()->onDelete('cascade');
            $table->foreignId('nurse_id')->constrained()->onDelete('cascade');
            $table->date('scheduled_date');
            $table->enum('shift', ['AM', 'PM', 'ON']);
            $table->timestamps();

            $table->unique(['bed_id', 'scheduled_date', 'shift'], 'ward_schedule_unique');
            $table->index(['ward_id', 'scheduled_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ward_schedule_assignments');
    }
};














