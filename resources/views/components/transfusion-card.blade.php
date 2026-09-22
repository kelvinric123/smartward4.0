@props(['transfusion', 'showPatient' => false])

{{--
    One blood unit, shown the same way on the Ward Infusion Overview and on a
    patient's Infusion Pump view. Blood is not driven by a pump, so these are
    kept in their own section rather than mixed into the infusion list: there
    is no device, no alarm feed, and the rate is the prescribed one.
--}}

@php
    $end = $transfusion->predictedEndAt();
    $limit = $transfusion->expiresRunningAt();
    $running = $transfusion->isRunning();
    $critical = $transfusion->hasCriticalException();
    $exceptions = $transfusion->exceptions();
@endphp

<div class="rounded-xl border-2 overflow-hidden shadow-md {{ $critical ? 'border-red-500 bg-gradient-to-br from-red-50 to-rose-50' : ($running ? 'border-rose-300 bg-gradient-to-br from-rose-50 to-pink-50' : 'border-gray-300 bg-white') }}"
    x-data="{
        now: Date.now(),
        started: {{ $transfusion->started_at?->getTimestampMs() ?? 'null' }},
        end: {{ $end?->getTimestampMs() ?? 'null' }},
        limit: {{ $limit?->getTimestampMs() ?? 'null' }},
        planned: {{ $transfusion->prescribed_minutes ?: 0 }},
        init() { setInterval(() => this.now = Date.now(), 1000); },
        hhmm(total) {
            const h = Math.floor(total / 60), m = total % 60;
            return h > 0 ? h + 'h ' + String(m).padStart(2, '0') + 'm' : m + 'm';
        },
        get elapsed() { return this.started === null ? 0 : Math.max(0, Math.floor((this.now - this.started) / 60000)); },
        get percent() { return this.planned ? Math.min(100, Math.round(this.elapsed / this.planned * 100)) : 0; },
        get overdue() { return this.end !== null && this.now > this.end; },
        get breached() { return this.limit !== null && this.now > this.limit; },
    }">
    <div class="px-3 py-2 bg-gradient-to-r {{ $critical ? 'from-red-600 to-rose-600' : ($running ? 'from-rose-500 to-pink-500' : 'from-gray-400 to-gray-500') }} text-white flex items-center justify-between">
        <div class="flex items-center gap-2 min-w-0">
            @if ($showPatient)
                <span class="font-bold text-sm">{{ $transfusion->patient->bed_number ?? 'N/A' }}</span>
                <span class="text-xs truncate opacity-90">{{ $transfusion->patient->name ?? '' }}</span>
            @else
                <span class="font-bold text-sm">{{ $transfusion->unit_number }}</span>
            @endif
        </div>
        <span class="text-xs bg-white/20 px-2 py-0.5 rounded uppercase font-semibold shrink-0">
            {{ $running ? 'Transfusing' : $transfusion->status }}
        </span>
    </div>

    <div class="p-3">
        <div class="flex items-center justify-between gap-2 mb-2">
            <div class="min-w-0">
                @if ($showPatient)
                    <div class="text-sm font-semibold text-gray-800 truncate">{{ $transfusion->unit_number }}</div>
                @endif
                <div class="text-xs text-gray-600">{{ $transfusion->product_type }}</div>
            </div>
            @if ($transfusion->unit_blood_group)
                <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800">
                    {{ $transfusion->unit_blood_group }}
                </span>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
                <div class="text-gray-500">Started</div>
                <div class="font-semibold text-gray-800">
                    {{ $transfusion->started_at?->format('d M H:i') ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Rate</div>
                <div class="font-semibold text-gray-800">
                    {{ $transfusion->rateMlPerHour() ? $transfusion->rateMlPerHour() . ' mL/hr' : '-' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Predicted end</div>
                <div class="font-semibold" :class="overdue ? 'text-red-700' : 'text-gray-800'">
                    {{ $end?->format('d M H:i') ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Elapsed</div>
                <div class="font-semibold" :class="breached ? 'text-red-700' : 'text-gray-800'"
                    x-text="started === null ? '-' : hhmm(elapsed)"></div>
            </div>
        </div>

        @if ($running && $transfusion->prescribed_minutes)
            <div class="mt-2">
                <div class="h-1.5 w-full rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-1.5 rounded-full transition-all" :class="overdue ? 'bg-red-500' : 'bg-emerald-500'"
                        :style="'width: ' + percent + '%'"></div>
                </div>
                <div class="mt-1 text-[11px] text-gray-500"
                    x-text="percent + '% of ' + hhmm(planned) + (overdue ? ' - overdue' : '')"></div>
            </div>
        @endif

        @if ($exceptions)
            <div class="mt-2 space-y-1">
                @foreach ($exceptions as $exception)
                    @php
                        $tone = match ($exception['level']) {
                            'critical' => 'bg-red-100 text-red-800',
                            'warning' => 'bg-amber-100 text-amber-800',
                            default => 'bg-blue-50 text-blue-800',
                        };
                    @endphp
                    <div class="rounded px-2 py-1 text-[11px] {{ $tone }}">
                        <span class="font-semibold">{{ $exception['title'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
