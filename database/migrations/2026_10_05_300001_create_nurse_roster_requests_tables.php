<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leave and shift-swap requests a nurse makes from the nurse app, for the
        // nurse manager to approve on the AI Nurse Schedule
        Schema::create('nurse_roster_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nurse_id')->constrained()->cascadeOnDelete();
            // 'leave' or 'swap'
            $table->string('type', 16);

            // Leave: the kind (NurseLeave::TYPES) and the days, inclusive
            $table->string('leave_type', 32)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Swap: the requester's shift on a day, and the colleague asked to take it.
            // colleague_shift is the colleague's shift that day the requester takes in
            // return; null when the requester only gives the shift away.
            $table->date('shift_date')->nullable();
            $table->string('shift', 8)->nullable();
            $table->foreignId('colleague_id')->nullable()->constrained('nurses')->nullOnDelete();
            $table->string('colleague_shift', 8)->nullable();
            $table->timestamp('colleague_responded_at')->nullable();

            $table->string('note', 255)->nullable();
            // awaiting_colleague (a swap), pending (with the nurse manager), approved, declined, cancelled
            $table->string('status', 24);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->timestamps();

            $table->index(['ward_id', 'status']);
            $table->index(['nurse_id', 'status']);
            $table->index(['colleague_id', 'status']);
        });

        // A nurse confirming they have seen their roster for a week. The fingerprint is
        // the roster as they saw it, so a later change asks them to look again.
        Schema::create('nurse_roster_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nurse_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->string('fingerprint', 64);
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(['nurse_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nurse_roster_acknowledgements');
        Schema::dropIfExists('nurse_roster_requests');
    }
};
