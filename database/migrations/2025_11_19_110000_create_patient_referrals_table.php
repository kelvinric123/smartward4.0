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
        Schema::create('patient_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->enum('referral_type', ['consultant', 'anaesthetist']);
            $table->foreignId('consultant_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('anaesthetist_id')->nullable()->constrained()->onDelete('set null');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active'); // active, completed, cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_referrals');
    }
};


