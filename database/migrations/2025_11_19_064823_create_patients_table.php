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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mrn')->unique(); // Medical Record Number
            $table->string('rn')->unique(); // Registration Number
            $table->string('ic_passport')->unique(); // IC or Passport Number
            $table->integer('age');
            $table->enum('gender', ['Male', 'Female']);
            $table->string('phone');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
