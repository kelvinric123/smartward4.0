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
        Schema::create('admission_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('ward_id')->constrained('wards')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('bed_number');
            $table->string('action'); // 'admit' or 'prebook' or 'check-in'
            $table->string('patient_name');
            $table->string('mrn');
            $table->string('consultant_name')->nullable();
            $table->string('nurse_name')->nullable();
            $table->string('gender')->nullable();
            $table->integer('age')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('admitted_at')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_logs');
    }
};
