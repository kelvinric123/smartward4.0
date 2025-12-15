<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds additional fields required for ADT integration
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Date of birth for age calculation
            $table->date('date_of_birth')->nullable()->after('gender');
            
            // Demographics
            $table->string('race')->nullable()->after('date_of_birth');
            $table->string('religion')->nullable()->after('race');
            
            // Address fields (JSON for flexibility)
            $table->json('address')->nullable()->after('phone');
            
            // Visit/Admission tracking
            $table->string('visit_number')->nullable()->after('status');
            $table->timestamp('expected_discharge_at')->nullable()->after('admitted_at');
            $table->integer('estimated_length_of_stay')->nullable()->after('expected_discharge_at');
            
            // Patient class (Inpatient, Outpatient, Emergency, etc.)
            $table->string('patient_class')->nullable()->after('visit_number');
            
            // Add index for visit number lookups
            $table->index('visit_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['visit_number']);
            $table->dropColumn([
                'date_of_birth',
                'race',
                'religion',
                'address',
                'visit_number',
                'expected_discharge_at',
                'estimated_length_of_stay',
                'patient_class',
            ]);
        });
    }
};




















