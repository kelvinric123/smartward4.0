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
        Schema::create('vital_sign_monitor_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Device name/identifier');
            $table->string('ip_address', 45)->comment('Device IP address');
            $table->integer('port')->default(24105)->comment('Device port');
            $table->string('location')->nullable()->comment('Physical location of the device');
            $table->text('description')->nullable()->comment('Additional device description');
            $table->boolean('is_active')->default(true)->comment('Whether the device is active');
            $table->timestamp('last_connected_at')->nullable()->comment('Last successful connection time');
            $table->string('last_status')->nullable()->comment('Last connection status');
            $table->timestamps();
            
            // Unique constraint on IP address
            $table->unique('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_sign_monitor_devices');
    }
};








