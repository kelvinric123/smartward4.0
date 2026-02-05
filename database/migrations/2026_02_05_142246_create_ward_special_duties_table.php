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
        Schema::create('ward_special_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained('wards')->onDelete('cascade');
            $table->foreignId('nurse_id')->nullable()->constrained('nurses')->onDelete('cascade');
            $table->date('date');
            $table->string('shift'); // AM, PM, ON
            $table->string('duty_type'); // team_leader, dda_mc, etc.
            $table->timestamps();

            $table->index(['ward_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ward_special_duties');
    }
};
