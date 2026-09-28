<?php

use App\Support\ClinicalIndicatorLibrary;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Adds Ventilator & Airway Parameters (VENT), recorded as readings like
     * HEMO, to installs that already ran the library sync. Sync matches on
     * code, so existing rows keep their ids and ward-type links. Which ward
     * types record it (ICU, HDU...) is chosen on the Ward Types page; bound to
     * a critical care ward type, it also shows on the Critical Care Ward
     * Dashboard bed cards.
     */
    public function up(): void
    {
        ClinicalIndicatorLibrary::sync();
    }

    public function down(): void
    {
        // Left in place: removing the row would also drop any readings recorded against it.
    }
};
