<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_flow_command_centres', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('login_password');
        });
    }

    public function down(): void
    {
        Schema::table('patient_flow_command_centres', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
