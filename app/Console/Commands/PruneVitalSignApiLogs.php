<?php

namespace App\Console\Commands;

use App\Services\VitalSignApiLogPruner;
use Illuminate\Console\Command;

class PruneVitalSignApiLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vital-sign:prune-api-logs {--days= : Retention in days (overrides VITAL_SIGN_API_LOG_RETENTION_DAYS)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Roll vital sign API logs older than the retention period into daily summaries, then delete them';

    /**
     * Execute the console command.
     */
    public function handle(VitalSignApiLogPruner $pruner): int
    {
        $days = (int) ($this->option('days') ?? config('services.vital_sign_api.log_retention_days', 7));
        $cutoff = now()->subDays($days)->startOfDay();

        $this->info("Pruning vital sign API logs older than {$cutoff->toDateTimeString()} ({$days} days retention)...");

        $result = $pruner->summarizeAndDelete($cutoff);

        $this->info("Summarized {$result['summarized']} daily groups, deleted {$result['deleted']} log rows.");

        return self::SUCCESS;
    }
}
