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
        // First, convert existing diet_type values to JSON array format
        // Get all patients with diet_type
        $patients = DB::table('patients')->whereNotNull('diet_type')->get();
        
        foreach ($patients as $patient) {
            if (!empty($patient->diet_type)) {
                // Convert single value to array format
                $dietTypes = json_encode([$patient->diet_type]);
                DB::table('patients')
                    ->where('id', $patient->id)
                    ->update(['diet_type' => $dietTypes]);
            }
        }

        // Now change the column type to JSON
        Schema::table('patients', function (Blueprint $table) {
            $table->json('diet_types')->nullable()->after('nursing_level');
        });

        // Copy data from diet_type to diet_types
        DB::statement('UPDATE patients SET diet_types = diet_type WHERE diet_type IS NOT NULL');

        // Drop the old column
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('diet_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the old column
        Schema::table('patients', function (Blueprint $table) {
            $table->string('diet_type')->nullable()->default('regular')->after('nursing_level');
        });

        // Get all patients with diet_types
        $patients = DB::table('patients')->whereNotNull('diet_types')->get();
        
        foreach ($patients as $patient) {
            if (!empty($patient->diet_types)) {
                $dietTypes = json_decode($patient->diet_types, true);
                // Get first value from array
                $firstDiet = is_array($dietTypes) && count($dietTypes) > 0 ? $dietTypes[0] : 'regular';
                DB::table('patients')
                    ->where('id', $patient->id)
                    ->update(['diet_type' => $firstDiet]);
            }
        }

        // Drop the new column
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('diet_types');
        });
    }
};





