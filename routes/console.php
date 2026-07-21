<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule Nurse EKad Update (15 minutes before shift starts)
// Shifts: AM (07:00), PM (14:00), ON (23:00)
Schedule::command('ekad:update-shift-nurses --shift=AM')->dailyAt('06:45');
Schedule::command('ekad:update-shift-nurses --shift=PM')->dailyAt('13:45');
Schedule::command('ekad:update-shift-nurses --shift=ON')->dailyAt('22:45');

// Roll old vital sign API logs into daily summaries and delete the raw rows
// (retention configurable via VITAL_SIGN_API_LOG_RETENTION_DAYS, default 7 days).
Schedule::command('vital-sign:prune-api-logs')->dailyAt('02:30');
