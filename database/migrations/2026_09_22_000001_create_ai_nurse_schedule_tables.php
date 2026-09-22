<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The AI Nurse Schedule: a staff roster on top of the existing bed roster.
     *
     * nurse_roster_entries says which shift each nurse works on each day (AM,
     * PM or ON, or OFF when a day off is fixed). One entry per nurse per day,
     * across all wards, so nobody is booked twice. "auto" entries are the
     * generator's and are replaced when it runs again; "manual" ones are kept.
     *
     * Which beds a nurse covers on a shift stays in ward_schedule_assignments,
     * the table the ward dashboard, the Ward Schedule page and the bedside
     * screens already read, so both pages work on the same assignments.
     */
    public function up(): void
    {
        Schema::create('nurse_roster_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->foreignId('nurse_id')->constrained('nurses')->cascadeOnDelete();
            $table->date('roster_date');
            $table->string('shift', 3);
            $table->string('source', 10)->default('manual');
            $table->timestamps();

            $table->unique(['nurse_id', 'roster_date'], 'nurse_roster_nurse_date_unique');
            $table->index(['ward_id', 'roster_date'], 'nurse_roster_ward_date_index');
        });

        Schema::create('nurse_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nurse_id')->constrained('nurses')->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['nurse_id', 'start_date', 'end_date'], 'nurse_leaves_nurse_dates_index');
        });

        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // Per-ward staffing rules; anything not set falls back to defaults in code
        Schema::create('ward_roster_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->unique()->constrained('wards')->cascadeOnDelete();
            $table->json('rules')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ward_roster_settings');
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('nurse_leaves');
        Schema::dropIfExists('nurse_roster_entries');
    }
};
