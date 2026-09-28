<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A hospital's theme colours (null keeps the built-in blue/cyan) and the
     * sidebar menu items it hides, set on the Theme & Menu tab of the
     * hospital's edit page.
     */
    public function up(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->string('theme_primary_color', 7)->nullable()->after('navbar_logo_path');
            $table->string('theme_secondary_color', 7)->nullable()->after('theme_primary_color');
            $table->json('hidden_nav_items')->nullable()->after('theme_secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropColumn(['theme_primary_color', 'theme_secondary_color', 'hidden_nav_items']);
        });
    }
};
