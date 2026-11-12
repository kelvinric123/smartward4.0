<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A name for each uploaded slide, shown in the slideshow's slide picker:
     * the uploaded file's name to start with, editable on the Slideshow page.
     * Slides uploaded before this have none and fall back to their stored file name.
     */
    public function up(): void
    {
        Schema::table('slideshows', function (Blueprint $table) {
            $table->string('title', 100)->nullable()->after('file_type');
        });
    }

    public function down(): void
    {
        Schema::table('slideshows', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
