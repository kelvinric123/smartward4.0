<?php

namespace App\Services;

use App\Models\VitalSignApiLog;
use App\Models\VitalSignApiLogSummary;
use Carbon\CarbonInterface;

class VitalSignApiLogPruner
{
    /**
     * Roll the matching raw API logs up into daily summaries, then delete them.
     *
     * @param  CarbonInterface|null  $before     only logs created before this moment (null = all)
     * @param  int|null              $apiUserId  restrict to one API user (null = all users)
     * @return array{summarized: int, deleted: int}
     */
    public function summarizeAndDelete(?CarbonInterface $before = null, ?int $apiUserId = null): array
    {
        $base = VitalSignApiLog::query()
            ->when($before, fn ($q) => $q->where('created_at', '<', $before))
            ->when($apiUserId, fn ($q) => $q->where('api_user_id', $apiUserId));

        $groups = (clone $base)
            ->selectRaw('DATE(created_at) as summary_date')
            ->selectRaw('api_user_id, endpoint, method')
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count')
            ->selectRaw('AVG(response_time_ms) as avg_response_time_ms')
            ->selectRaw('MAX(response_time_ms) as max_response_time_ms')
            ->groupBy('summary_date', 'api_user_id', 'endpoint', 'method')
            ->get();

        foreach ($groups as $group) {
            $summary = VitalSignApiLogSummary::firstOrNew([
                'summary_date' => $group->summary_date,
                'api_user_id' => $group->api_user_id,
                'endpoint' => $group->endpoint,
                'method' => $group->method,
            ]);

            // A date can be summarized more than once (e.g. a manual clear followed by
            // the scheduled prune), so merge counts and weight the average.
            $oldTotal = (int) $summary->total_requests;
            $newTotal = $oldTotal + (int) $group->total_requests;
            if ($newTotal > 0) {
                $summary->avg_response_time_ms = (int) round(
                    ((float) ($summary->avg_response_time_ms ?? 0) * $oldTotal
                        + (float) ($group->avg_response_time_ms ?? 0) * (int) $group->total_requests)
                    / $newTotal
                );
            }
            $summary->total_requests = $newTotal;
            $summary->success_count = (int) $summary->success_count + (int) $group->success_count;
            $summary->error_count = (int) $summary->error_count + (int) $group->error_count;
            $summary->max_response_time_ms = max((int) ($summary->max_response_time_ms ?? 0), (int) ($group->max_response_time_ms ?? 0));
            $summary->save();
        }

        // Delete in chunks to avoid holding a long lock on a busy production table.
        $deleted = 0;
        do {
            $count = (clone $base)->limit(5000)->delete();
            $deleted += $count;
        } while ($count > 0);

        return ['summarized' => $groups->count(), 'deleted' => $deleted];
    }
}
