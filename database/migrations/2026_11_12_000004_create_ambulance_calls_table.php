<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ambulance calls to the ED, for Command Center V2 (ED)'s "Ambulance cases
 * today" card: when each call came in, when the crew reached the patient (the
 * response time, and a case attended) or the call was cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulance_calls', function (Blueprint $table) {
            $table->id();
            // The dispatch system's own number for the call, so a resend updates it rather than adding another
            $table->string('reference', 64)->nullable()->unique();
            $table->dateTime('called_at')->index();
            $table->dateTime('dispatched_at')->nullable();
            // The crew with the patient: the case attended, and where the response time ends
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('source', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_calls');
    }
};
