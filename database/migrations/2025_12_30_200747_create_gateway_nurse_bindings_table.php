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
        Schema::create('gateway_nurse_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_user_id')->constrained()->onDelete('cascade');
            $table->foreignId('nurse_id')->constrained()->onDelete('cascade');
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->timestamps();

            // Index for quick lookup of active bindings
            $table->index(['api_user_id', 'end_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gateway_nurse_bindings');
    }
};
