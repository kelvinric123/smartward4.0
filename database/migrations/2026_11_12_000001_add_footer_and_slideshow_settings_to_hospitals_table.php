<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bottom bar's text, set on the Theme & Menu tab of the hospital's edit
     * page (both null keeps "PHKL Smart Ward 4.0"), and the ward dashboard
     * slideshow's settings, set on the hospital's Slideshow page: whether the
     * dashboard logo opens it, the seconds each slide shows, and the built-in
     * pictures (the files in base_path('picture')) the hospital hides.
     */
    public function up(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->string('footer_highlight', 40)->nullable()->after('hidden_nav_items');
            $table->string('footer_text', 80)->nullable()->after('footer_highlight');
            $table->boolean('slideshow_enabled')->default(true)->after('footer_text');
            $table->unsignedSmallInteger('slideshow_seconds')->default(10)->after('slideshow_enabled');
            $table->json('slideshow_hidden_pictures')->nullable()->after('slideshow_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropColumn(['footer_highlight', 'footer_text', 'slideshow_enabled', 'slideshow_seconds', 'slideshow_hidden_pictures']);
        });
    }
};
