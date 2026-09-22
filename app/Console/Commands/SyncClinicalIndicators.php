<?php

namespace App\Console\Commands;

use App\Support\ClinicalIndicatorLibrary;
use Illuminate\Console\Command;

class SyncClinicalIndicators extends Command
{
    protected $signature = 'clinical-indicators:sync';

    protected $description = 'Upsert the clinical indicator library (app/Support/ClinicalIndicatorLibrary.php) into the database';

    public function handle(): int
    {
        $result = ClinicalIndicatorLibrary::sync();

        $this->info('Clinical indicators synced from the library.');
        $this->line("- created: {$result['created']}");
        $this->line("- updated: {$result['updated']}");

        $pending = array_filter(
            ClinicalIndicatorLibrary::INDICATORS,
            fn ($definition) => !($definition['confirmed'] ?? false)
        );

        if ($pending) {
            $this->newLine();
            $this->warn('Awaiting confirmation of the local variant (no detail recorded):');
            foreach ($pending as $definition) {
                $this->line("- {$definition['code']} ({$definition['name']})");
            }
        }

        return self::SUCCESS;
    }
}
