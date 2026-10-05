<?php

use App\Support\ClinicalIndicatorLibrary;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Adds Mental State Assessment (C-SSRS), the Columbia suicide risk screen
     * under the new Mental state category, to installs that already ran the
     * library sync. Sync matches on code, so existing rows keep their ids and
     * ward-type links. Which ward types screen with it is chosen on the Ward
     * Types page; the patient details on the ward dashboard then show its form.
     */
    public function up(): void
    {
        ClinicalIndicatorLibrary::sync();
    }

    public function down(): void
    {
        // Left in place: removing the row would also drop any screens recorded against it.
    }
};
