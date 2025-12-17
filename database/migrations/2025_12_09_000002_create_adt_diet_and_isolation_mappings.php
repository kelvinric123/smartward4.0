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
        Schema::create('adt_diet_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adt_configuration_id')->constrained()->cascadeOnDelete();
            $table->string('adt_diet_code');
            $table->string('adt_diet_name')->nullable();
            $table->string('mapped_diet')->nullable(); // e.g., diabetic, regular, contact_isolation
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['adt_configuration_id', 'adt_diet_code'], 'adt_diet_cfg_code_unique');
        });

        Schema::create('adt_isolation_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adt_configuration_id')->constrained()->cascadeOnDelete();
            $table->string('adt_isolation_code');
            $table->string('adt_isolation_name')->nullable();
            $table->string('mapped_isolation')->nullable(); // e.g., contact, airborne, droplet
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['adt_configuration_id', 'adt_isolation_code'], 'adt_iso_cfg_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adt_isolation_mappings');
        Schema::dropIfExists('adt_diet_mappings');
    }
};





















