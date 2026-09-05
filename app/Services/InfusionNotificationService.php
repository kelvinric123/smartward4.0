<?php

namespace App\Services;

use App\Models\Infusion;
use App\Models\InfusionPump;
use App\Models\WardNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Turns live infusion state into the ward's two infusion signals: the
 * notification bell, and the infusion indicator on each bed box. Both are
 * derived from the same events here, so the bed box and the bell can never
 * disagree about how bad a pump looks.
 *
 * Pump events reach the nurse through the same bell as EWS scores and patient
 * smart calls, colour-coded the same way: red for anything that needs someone
 * at the bedside now, yellow for anything that needs someone before long.
 *
 *   RED     pump alarm at high priority (or of unknown priority), battery
 *           critical, and the last few minutes of a running infusion
 *   YELLOW  pump alarm at medium/low/technical priority, battery low,
 *           infusion approaching its end, infusion finished
 *
 * Source-agnostic: in Qmed engine mode the state is read live from the engine
 * (the local `infusions` table is not written in that mode), otherwise from
 * the database. An unreachable engine never breaks the notification poll - it
 * backs off and the existing notifications simply stand.
 */
class InfusionNotificationService
{
    /** Running infusion: minutes remaining at which we warn, then escalate. */
    const NEAR_END_WARNING_MINUTES = 15;
    const NEAR_END_URGENT_MINUTES = 5;

    /** One ward syncs at most this often, however many dashboards are polling. */
    const SYNC_THROTTLE_SECONDS = 15;

    /** How long to stop calling a failing engine before trying again. */
    const ENGINE_BACKOFF_SECONDS = 60;

    /**
     * Infusions whose last report is older than this raise nothing. A pump
     * that has been silent for half a day is not telling us about the ward as
     * it is now, and history left in the table must not surface as a live
     * alert the first time this runs.
     */
    const STALE_AFTER_HOURS = 12;

    /** Bed-box indicators are re-derived at most this often per ward. */
    const INDICATOR_CACHE_SECONDS = 10;

    /**
     * Live infusions already fetched this request, keyed by ward. The bell and
     * the bed boxes both want them on a dashboard load; without this the page
     * would call the engine twice.
     *
     * @var array<int,Collection>
     */
    protected array $wardInfusionMemo = [];

    /**
     * Sync one ward, respecting the throttle. Safe to call on every poll.
     *
     * @return array{created:int,updated:int,closed:int,skipped:bool}
     */
    public function syncWardThrottled(int $wardId): array
    {
        $idle = ['created' => 0, 'updated' => 0, 'closed' => 0, 'skipped' => true];

        // Cache::add is atomic, so concurrent dashboards on the same ward do
        // the work once rather than each running a full engine round-trip.
        if (!Cache::add("infusion-notify:ward:{$wardId}", 1, self::SYNC_THROTTLE_SECONDS)) {
            return $idle;
        }

        try {
            return $this->syncWard($wardId) + ['skipped' => false];
        } catch (\Throwable $e) {
            Log::warning('Infusion notification sync failed', [
                'ward_id' => $wardId,
                'error' => $e->getMessage(),
            ]);

            return $idle;
        }
    }

    /**
     * Sync one ward now.
     *
     * @return array{created:int,updated:int,closed:int}
     */
    public function syncWard(int $wardId): array
    {
        $events = $this->currentEvents($wardId);

        $liveByReference = $events->keyBy('reference');

        $existing = WardNotification::where('ward_id', $wardId)
            ->where('type', WardNotification::TYPE_INFUSION)
            ->whereNotNull('reference')
            ->orderByDesc('id')
            ->get()
            ->unique('reference')
            ->keyBy('reference');

        $created = 0;
        $updated = 0;
        $closed = 0;

        foreach ($liveByReference as $reference => $event) {
            $latest = $existing->get($reference);

            if (!$latest) {
                WardNotification::create($event + ['status' => WardNotification::STATUS_PENDING]);
                $created++;
                continue;
            }

            if ($latest->status === WardNotification::STATUS_PENDING) {
                // Same episode, fresher numbers (rate, volume, alarm text) and
                // possibly a worse severity as the infusion runs down.
                $latest->fill($event)->save();
                $updated++;
                continue;
            }

            // Answered already. Only raise it again once the condition has
            // actually gone away and come back - otherwise a pump that keeps
            // reporting a silenced alarm would refill the queue every poll.
            if ($this->episodeEnded($latest)) {
                WardNotification::create($event + ['status' => WardNotification::STATUS_PENDING]);
                $created++;
            }
        }

        // Everything on file that the pumps are no longer reporting: the alarm
        // was cleared, the bag was changed, the pump went back on mains.
        foreach ($existing as $reference => $notification) {
            if ($liveByReference->has($reference) || $this->episodeEnded($notification)) {
                continue;
            }

            $meta = $notification->meta ?? [];
            $meta['episode_ended_at'] = now()->toDateTimeString();
            $notification->meta = $meta;

            $selfClearing = in_array(
                $notification->category,
                WardNotification::SELF_CLEARING_INFUSION_CATEGORIES,
                true
            );

            // A finished infusion still leaves a line to flush or remove, so
            // that one waits for a nurse even once the pump goes quiet.
            if ($selfClearing && $notification->status === WardNotification::STATUS_PENDING) {
                $notification->status = WardNotification::STATUS_RESPONDED;
                $notification->responded_at = now();
                $closed++;
            }

            $notification->save();
        }

        return ['created' => $created, 'updated' => $updated, 'closed' => $closed];
    }

    // ------------------------------------------------------------- bed boxes

    /**
     * Per-patient infusion indicator for the bed box.
     *
     * `state` is the worst thing currently true of that patient's pumps, using
     * exactly the severity rules the bell uses:
     *
     *   none     nothing attached          (blue - the resting state)
     *   running  delivering, nothing wrong (green)
     *   warning  a yellow event            (ending soon, finished, battery low,
     *                                       medium/low-priority alarm)
     *   urgent   a red event               (high/unclassified alarm, minutes
     *                                       from empty, battery critical)
     *
     * `count` is how many infusions are actually attached and delivering, which
     * is what the little number on the icon shows. A finished infusion raises a
     * yellow state but adds nothing to the count - there is nothing running any
     * more, only a line left to deal with.
     *
     * @return array<int,array{count:int,state:string,summary:string}>
     */
    public function bedIndicators(int $wardId): array
    {
        return Cache::remember(
            "infusion-bed-state:{$wardId}",
            self::INDICATOR_CACHE_SECONDS,
            fn () => $this->buildBedIndicators($wardId)
        );
    }

    /**
     * @return array<int,array{count:int,state:string,summary:string}>
     */
    protected function buildBedIndicators(int $wardId): array
    {
        $rank = ['none' => 0, 'running' => 1, 'warning' => 2, 'urgent' => 3];
        $indicators = [];

        foreach ($this->wardInfusions($wardId) as $item) {
            /** @var Infusion $infusion */
            $infusion = $item['infusion'];
            $patient = $infusion->patient;

            if (!$patient || (int) $patient->ward_id !== $wardId || $this->isStale($infusion)) {
                continue;
            }

            $id = $patient->id;
            $indicators[$id] ??= ['count' => 0, 'state' => 'none', 'summary' => []];

            $attached = in_array($infusion->status, [
                Infusion::STATUS_RUNNING,
                Infusion::STATUS_PAUSED,
                Infusion::STATUS_ALARMING,
            ], true);

            if ($attached) {
                $indicators[$id]['count']++;
            }

            $state = $infusion->status === Infusion::STATUS_RUNNING ? 'running' : 'none';

            foreach ($this->eventsFor($infusion, $item['pump'], $wardId) as $event) {
                $eventState = $event['severity'] === WardNotification::SEVERITY_URGENT
                    ? 'urgent'
                    : 'warning';

                if ($rank[$eventState] > $rank[$state]) {
                    $state = $eventState;
                }
            }

            if ($rank[$state] > $rank[$indicators[$id]['state']]) {
                $indicators[$id]['state'] = $state;
            }

            $indicators[$id]['summary'][] = $this->indicatorLine($infusion);
        }

        // Drop patients whose pumps turned out to have nothing to say, and
        // flatten the per-infusion lines into one tooltip.
        return collect($indicators)
            ->reject(fn (array $i) => $i['count'] === 0 && $i['state'] === 'none')
            ->map(fn (array $i) => [
                'count' => $i['count'],
                'state' => $i['state'],
                'summary' => implode("\n", $i['summary']),
            ])
            ->all();
    }

    /**
     * One line of the bed box tooltip: what is in the line and what it is doing.
     */
    protected function indicatorLine(Infusion $infusion): string
    {
        $parts = [$infusion->medication_name ?: 'Infusion'];

        if ($infusion->status === Infusion::STATUS_ALARMING) {
            $parts[] = 'ALARM: ' . ($infusion->alarm_message ?: $infusion->alarm_type ?: 'pump alarm');
        } elseif ($infusion->status === Infusion::STATUS_COMPLETED) {
            $parts[] = 'finished';
        } elseif ($infusion->status === Infusion::STATUS_PAUSED) {
            $parts[] = 'paused';
        }

        if ($infusion->flow_rate !== null) {
            $parts[] = rtrim(rtrim(number_format((float) $infusion->flow_rate, 1), '0'), '.') . ' mL/hr';
        }

        if ($infusion->remaining_minutes !== null && $infusion->status === Infusion::STATUS_RUNNING) {
            $parts[] = $infusion->remaining_minutes . ' min left';
        }

        return implode(' · ', $parts);
    }

    /**
     * An episode is over once the condition stopped being reported; the next
     * occurrence of the same condition is then a genuinely new event.
     */
    protected function episodeEnded(WardNotification $notification): bool
    {
        return !empty($notification->meta['episode_ended_at']);
    }

    // ---------------------------------------------------------------- sources

    /**
     * Every notification-worthy infusion event in the ward right now.
     */
    protected function currentEvents(int $wardId): Collection
    {
        return $this->wardInfusions($wardId)
            ->flatMap(fn (array $item) => $this->eventsFor($item['infusion'], $item['pump'], $wardId))
            ->filter()
            ->values();
    }

    /**
     * Ward infusions paired with the pump state to judge them by.
     *
     * @return Collection<int,array{infusion:Infusion,pump:?InfusionPump}>
     */
    protected function wardInfusions(int $wardId): Collection
    {
        return $this->wardInfusionMemo[$wardId] ??= $this->fetchWardInfusions($wardId);
    }

    protected function fetchWardInfusions(int $wardId): Collection
    {
        if (InfusionEngineClient::engineModeActive()) {
            if (Cache::has('infusion-notify:engine-down')) {
                return collect();
            }

            try {
                return $this->engineInfusions($wardId);
            } catch (\Throwable $e) {
                // Stop hammering an unreachable engine on every 20s poll.
                Cache::put('infusion-notify:engine-down', 1, self::ENGINE_BACKOFF_SECONDS);
                Log::warning('Infusion engine unreachable during notification sync: ' . $e->getMessage());

                return collect();
            }
        }

        return Infusion::with(['patient', 'infusionPump'])
            ->inWard($wardId)
            ->whereIn('status', [
                Infusion::STATUS_RUNNING,
                Infusion::STATUS_PAUSED,
                Infusion::STATUS_ALARMING,
                Infusion::STATUS_COMPLETED,
            ])
            ->get()
            ->map(fn (Infusion $infusion) => [
                'infusion' => $infusion,
                'pump' => $infusion->infusionPump,
            ]);
    }

    /**
     * Whether this infusion's last report is too old to act on.
     *
     * Engine-built infusions are never persisted, so `updated_at` is only a
     * fallback for database rows; an infusion carrying no timestamp at all is
     * taken at face value as current state.
     */
    protected function isStale(Infusion $infusion): bool
    {
        $seenAt = $infusion->last_updated_at
            ?? $infusion->completed_at
            ?? $infusion->updated_at;

        return $seenAt !== null && $seenAt->lt(now()->subHours(self::STALE_AFTER_HOURS));
    }

    /**
     * Live engine state. Battery and power come from the engine snapshot, not
     * the registry row, which is only as fresh as the last registry sync.
     */
    protected function engineInfusions(int $wardId): Collection
    {
        return EngineInfusionService::make()
            ->linkedPairs()
            ->filter(fn (array $pair) => (int) ($pair['infusion']->patient->ward_id ?? 0) === $wardId)
            ->map(function (array $pair) {
                $pump = $pair['local'];
                $engine = $pair['engine'];

                // Non-persisted overlay: give the pump the engine's live power
                // readings so isBatteryLow()/isBatteryCritical() judge current
                // state. Not saved - this object is only used for this pass.
                if ($pump) {
                    if (isset($engine['power_status'])) {
                        $pump->power_status = $engine['power_status'];
                    }
                    if (isset($engine['battery_percent'])) {
                        $pump->battery_percent = (int) $engine['battery_percent'];
                    }
                    if (isset($engine['battery_minutes_remaining'])) {
                        $pump->battery_minutes_remaining = (int) $engine['battery_minutes_remaining'];
                    }
                }

                return ['infusion' => $pair['infusion'], 'pump' => $pump];
            })
            ->values();
    }

    // ----------------------------------------------------------------- events

    /**
     * Notification-worthy events for one infusion. A pump can raise more than
     * one at a time (alarming *and* nearly out of battery), so each is its own
     * notification with its own reference.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function eventsFor(Infusion $infusion, ?InfusionPump $pump, int $wardId): array
    {
        $patient = $infusion->patient;

        if (!$patient || (int) $patient->ward_id !== $wardId || $this->isStale($infusion)) {
            return [];
        }

        $bed = $patient->bed_number ?: '--';
        $name = $patient->name ?? 'Unknown patient';
        $drug = $infusion->medication_name ?: 'Infusion';
        $key = $this->referenceKey($infusion, $pump);

        $base = [
            'ward_id' => $wardId,
            'patient_id' => $patient->id,
            'bed_number' => $bed,
            'type' => WardNotification::TYPE_INFUSION,
            'ews_score' => null,
        ];

        $events = [];

        if ($alarm = $this->alarmEvent($infusion, $drug, $name, $bed, $key)) {
            $events[] = $base + $alarm;
        }

        if ($nearEnd = $this->nearEndEvent($infusion, $drug, $name, $bed, $key)) {
            $events[] = $base + $nearEnd;
        }

        if ($complete = $this->completionEvent($infusion, $drug, $name, $bed, $key)) {
            $events[] = $base + $complete;
        }

        if ($pump && ($battery = $this->batteryEvent($pump, $name, $bed, $key))) {
            $events[] = $base + $battery;
        }

        return $events;
    }

    /**
     * A pump alarm: occlusion, air-in-line, empty syringe, downstream pressure.
     * High priority means someone is needed at the bedside now.
     */
    protected function alarmEvent(Infusion $infusion, string $drug, string $name, string $bed, string $key): ?array
    {
        if ($infusion->status !== Infusion::STATUS_ALARMING) {
            return null;
        }

        $priority = $infusion->alarm_priority;
        $text = $infusion->alarm_message ?: $infusion->alarm_type ?: 'Pump alarm';

        // An alarm the pump did not classify is treated as high: a delivery
        // fault we cannot rank is not one to quietly downgrade to yellow.
        $urgent = $priority === null || $priority === '' || $priority === 'high';
        $severity = $urgent ? WardNotification::SEVERITY_URGENT : WardNotification::SEVERITY_WARNING;

        $message = $urgent
            ? "PUMP ALARM: {$name} (Bed {$bed}) — {$text} on {$drug}"
            : "Pump alarm: {$name} (Bed {$bed}) — {$text} on {$drug}";

        return [
            'category' => WardNotification::CATEGORY_INFUSION_ALARM,
            'reference' => $key . ':alarm',
            'severity' => $severity,
            'message' => $message,
            'meta' => $this->meta($infusion, [
                'event' => 'alarm',
                'alarm_type' => $infusion->alarm_type,
                'alarm_message' => $infusion->alarm_message,
                'alarm_priority' => $priority,
                'alarm_priority_label' => $infusion->alarm_priority_display ?: null,
            ]),
        ];
    }

    /**
     * A running infusion close to finishing - time to prepare the next bag or
     * plan the line. Turns red for the last few minutes, when an unattended
     * line starts risking clotting or air.
     */
    protected function nearEndEvent(Infusion $infusion, string $drug, string $name, string $bed, string $key): ?array
    {
        $minutes = $infusion->remaining_minutes;

        if ($infusion->status !== Infusion::STATUS_RUNNING || $minutes === null) {
            return null;
        }

        if ($minutes > self::NEAR_END_WARNING_MINUTES) {
            return null;
        }

        $urgent = $minutes <= self::NEAR_END_URGENT_MINUTES;
        $left = $minutes <= 0 ? 'less than a minute' : "{$minutes} min";

        return [
            'category' => WardNotification::CATEGORY_INFUSION_NEAR_END,
            'reference' => $key . ':near_end',
            'severity' => $urgent
                ? WardNotification::SEVERITY_URGENT
                : WardNotification::SEVERITY_WARNING,
            'message' => $urgent
                ? "INFUSION ENDING: {$name} (Bed {$bed}) — {$drug} has {$left} left"
                : "Infusion ending soon: {$name} (Bed {$bed}) — {$drug} has {$left} left",
            'meta' => $this->meta($infusion, ['event' => 'near_end']),
        ];
    }

    /**
     * The programmed volume has been delivered. The pump is quiet but the line
     * still needs flushing or removing, so this one waits for a nurse.
     */
    protected function completionEvent(Infusion $infusion, string $drug, string $name, string $bed, string $key): ?array
    {
        if ($infusion->status !== Infusion::STATUS_COMPLETED) {
            return null;
        }

        return [
            'category' => WardNotification::CATEGORY_INFUSION_COMPLETE,
            'reference' => $key . ':complete',
            'severity' => WardNotification::SEVERITY_WARNING,
            'message' => "Infusion complete: {$name} (Bed {$bed}) — {$drug} has finished",
            'meta' => $this->meta($infusion, ['event' => 'complete']),
        ];
    }

    /**
     * The pump is running on its own battery and will not last. Red once it is
     * critical, because a pump that dies mid-infusion stops delivery.
     */
    protected function batteryEvent(InfusionPump $pump, string $name, string $bed, string $key): ?array
    {
        if (!$pump->isBatteryLow() && !$pump->isBatteryCritical()) {
            return null;
        }

        $critical = $pump->isBatteryCritical();
        $percent = $pump->battery_percent;
        $label = $pump->device_name ?: $pump->serial_no ?: $pump->device_id;

        return [
            'category' => WardNotification::CATEGORY_INFUSION_BATTERY,
            'reference' => $key . ':battery',
            'severity' => $critical
                ? WardNotification::SEVERITY_URGENT
                : WardNotification::SEVERITY_WARNING,
            'message' => $critical
                ? "PUMP BATTERY CRITICAL: {$name} (Bed {$bed}) — {$label} at {$percent}%, connect to mains"
                : "Pump battery low: {$name} (Bed {$bed}) — {$label} at {$percent}%",
            'meta' => [
                'event' => 'battery',
                'pump_label' => $label,
                'battery_percent' => $percent,
                'battery_time_remaining' => $pump->formatted_battery_time,
                'power_status' => $pump->power_status,
            ],
        ];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Stable identity for one infusion on one pump. Two pumps alarming on the
     * same patient stay two notifications; the same pump re-reporting the same
     * alarm every poll updates one.
     */
    protected function referenceKey(Infusion $infusion, ?InfusionPump $pump): string
    {
        $pumpKey = $pump?->device_id ?: $pump?->serial_no ?: ('pump-' . ($infusion->infusion_pump_id ?? 'unknown'));
        $drug = strtolower(trim((string) ($infusion->medication_name ?: 'unknown')));
        $drug = preg_replace('/[^a-z0-9]+/', '-', $drug) ?: 'unknown';

        return 'infusion:' . $pumpKey . ':' . trim($drug, '-');
    }

    /**
     * The at-a-glance detail the modal shows under the message.
     */
    protected function meta(Infusion $infusion, array $extra = []): array
    {
        $pump = $infusion->infusionPump;

        return array_merge([
            'medication' => $infusion->medication_name,
            'flow_rate' => $infusion->flow_rate !== null ? (float) $infusion->flow_rate : null,
            'dose_rate' => $infusion->formatted_dose_rate,
            'remaining_minutes' => $infusion->remaining_minutes,
            'remaining_time' => $infusion->formatted_remaining_time,
            'remaining_volume' => $infusion->remaining_volume !== null
                ? (float) $infusion->remaining_volume : null,
            'total_volume' => $infusion->total_volume !== null
                ? (float) $infusion->total_volume : null,
            'progress_percent' => $infusion->progress_percent,
            'pump_label' => $pump?->device_name ?: $pump?->serial_no ?: $pump?->device_id,
            'infusion_status' => $infusion->status,
        ], $extra);
    }
}
