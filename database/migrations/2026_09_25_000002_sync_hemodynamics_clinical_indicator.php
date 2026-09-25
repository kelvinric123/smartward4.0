<?php

use App\Support\ClinicalIndicatorLibrary;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Adds Advanced Hemodynamics (Numerics) (HEMO), the first scale recorded as
     * monitor readings, to installs that already ran the library sync. Sync
     * matches on code, so existing rows keep their ids and ward-type links.
     * Which ward types record it (ICU, HDU...) is chosen on the Ward Types page.
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
