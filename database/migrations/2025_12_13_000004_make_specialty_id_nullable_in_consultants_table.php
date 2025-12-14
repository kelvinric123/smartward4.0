<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consultants', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['specialty_id']);
        });

        // Modify column to be nullable
        DB::statement('ALTER TABLE consultants MODIFY specialty_id BIGINT UNSIGNED NULL');

        // Re-add foreign key constraint
        Schema::table('consultants', function (Blueprint $table) {
            $table->foreign('specialty_id')->references('id')->on('specialties')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultants', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['specialty_id']);
        });

        // Modify column to be NOT NULL (will fail if there are null values)
        DB::statement('ALTER TABLE consultants MODIFY specialty_id BIGINT UNSIGNED NOT NULL');

        // Re-add foreign key constraint
        Schema::table('consultants', function (Blueprint $table) {
            $table->foreign('specialty_id')->references('id')->on('specialties')->onDelete('cascade');
        });
    }
};



