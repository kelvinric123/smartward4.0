<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fields of each SEEKINK template, edited on /ekad: the field names the
 * template was designed with, and what SmartWard puts in each. A template
 * without a row here gets the default fields (App\Models\EkadTemplate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ekad_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_id', 64)->unique();
            $table->string('name', 100)->nullable();
            // [{"key": "bed no", "source": "bed_number"}, {"key": "note", "source": "text", "value": "..."}, ...]
            $table->json('fields');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekad_templates');
    }
};
