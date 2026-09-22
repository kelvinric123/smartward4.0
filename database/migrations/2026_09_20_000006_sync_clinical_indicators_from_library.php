<?php

use App\Support\ClinicalIndicatorLibrary;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The scales now live in app/Support/ClinicalIndicatorLibrary.php rather
     * than in a seeder, so deploying brings the clinical content with it and
     * no separate db:seed step is needed. Matching is on code, so the rows
     * seeded earlier keep their ids and their ward-type links.
     *
     * Re-run later amendments with: php artisan clinical-indicators:sync
     */
    public function up(): void
    {
        ClinicalIndicatorLibrary::sync();
    }

    public function down(): void
    {
        // Nothing to undo: the rows predate this migration and dropping them
        // would take the ward-type links with them.
    }
};
