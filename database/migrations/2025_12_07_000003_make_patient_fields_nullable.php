<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Makes certain patient fields nullable for ADT integration
     * (HIS may not always provide all fields)
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Make age nullable (can be calculated from DOB or may not be provided)
            $table->integer('age')->nullable()->change();
            
            // Make gender nullable with enum values
            $table->string('gender')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->integer('age')->nullable(false)->change();
            $table->enum('gender', ['Male', 'Female'])->nullable(false)->change();
        });
    }
};




