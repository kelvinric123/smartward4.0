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
        Schema::table('patient_flow_command_centres', function (Blueprint $table) {
            $table->string('login_username')->nullable()->after('description');
            $table->string('login_password')->nullable()->after('login_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_flow_command_centres', function (Blueprint $table) {
            $table->dropColumn(['login_username', 'login_password']);
        });
    }
};
