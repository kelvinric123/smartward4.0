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
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('ward_id')->nullable()->after('is_active')->constrained('wards')->onDelete('set null');
            $table->string('bed_number')->nullable()->after('ward_id');
            $table->foreignId('consultant_id')->nullable()->after('bed_number')->constrained('consultants')->onDelete('set null');
            $table->foreignId('nurse_id')->nullable()->after('consultant_id')->constrained('nurses')->onDelete('set null');
            $table->timestamp('admitted_at')->nullable()->after('nurse_id');
            $table->timestamp('booked_at')->nullable()->after('admitted_at');
            $table->string('status')->default('prebook')->after('booked_at'); // prebook, admitted, discharged
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['ward_id']);
            $table->dropForeign(['consultant_id']);
            $table->dropForeign(['nurse_id']);
            $table->dropColumn(['ward_id', 'bed_number', 'consultant_id', 'nurse_id', 'admitted_at', 'booked_at', 'status']);
        });
    }
};
