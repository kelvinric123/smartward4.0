<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bedside terminals (the /terminal web app, the patient app) paired with a
     * bed. A nurse pairs a terminal with their nurse-app login; the terminal
     * then calls /api/terminal/* with the plain token, of which only the
     * SHA-256 hash is stored here. A revoked pairing keeps its row for the
     * record.
     */
    public function up(): void
    {
        Schema::create('terminal_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bed_id')->constrained('beds')->cascadeOnDelete();
            $table->string('name', 100)->nullable();
            $table->string('token', 64)->unique();
            $table->foreignId('paired_by_nurse_id')->nullable()->constrained('nurses')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['bed_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_devices');
    }
};
