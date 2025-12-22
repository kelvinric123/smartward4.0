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
        Schema::create('ekad_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('base_url')->default('http://iot.seekink.com/cloud/prod-api');
            $table->string('username');
            $table->string('password'); // Will be encrypted at model level
            $table->string('template_id');
            $table->text('bearer_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('auto_push_enabled')->default(false);
            $table->boolean('mask_patient_name')->default(false);
            $table->enum('mask_style', ['partial', 'full', 'initials'])->default('partial');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ekad_configurations');
    }
};
