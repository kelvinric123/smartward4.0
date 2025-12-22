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
        Schema::create('ekad_bed_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bed_id')->constrained()->onDelete('cascade');
            $table->string('mac_address'); // E-Ink device MAC (no colons, e.g., D43D393CC02C)
            $table->string('device_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['bed_id', 'mac_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ekad_bed_mappings');
    }
};
