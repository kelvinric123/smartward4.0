<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How SmartWard looks for each user (App\Support\UserTheme): display mode
     * and colours. Null for everyone until they choose, which keeps the
     * hospital's theme in Normal mode.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('theme')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
