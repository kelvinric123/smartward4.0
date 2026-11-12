<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A template's isolation templates: for a patient with an isolation
 * precaution, the screen is painted with another SEEKINK template that
 * carries that precaution's picture, with the same fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ekad_templates', function (Blueprint $table) {
            // {"AI": "2105503882906267648", "CI": "...", ...}: Isolation Types code => SEEKINK template ID
            $table->json('isolation_templates')->nullable()->after('fields');
        });
    }

    public function down(): void
    {
        Schema::table('ekad_templates', function (Blueprint $table) {
            $table->dropColumn('isolation_templates');
        });
    }
};
