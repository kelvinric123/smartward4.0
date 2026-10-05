{{--
    Patient Details > Oxygen Therapy: the oxygen the patient is on, changing it (or to
    room air), and how it has progressed this admission against SpO2. Changes made here
    and the oxygen recorded with vital signs are read together (OxygenTherapyChart).
    Expects $patient and $oxygen (App\Support\OxygenTherapyChart::forPatient).
--}}
@php
    $otc = \App\Models\OxygenTherapyChange::class;
    $deliveryOptions = \App\Models\VitalSign::OXYGEN_DELIVERY_OPTIONS;

    $current = $oxygen['current'];
    $onOxygen = (bool) ($current['on_oxygen'] ?? false);
    $currentTarget = $current['target'] ?? null;
    $latestSpo2 = $oxygen['latest_spo2'];
    $spo2State = $latestSpo2 && !$latestSpo2['stale'] ? $latestSpo2['state'] : null;
    $chart = $oxygen['chart'];
    $hasChart = $chart['steps'] || $chart['spo2'];

    // Device colours, cool to warm as the support rises; shared with the chart
    $deviceColors = [
        'room_air' => '#9ca3af',
        'nasal_cannula' => '#38bdf8',
        'simple_mask' => '#22d3ee',
        'venturi_mask' => '#2dd4bf',
        'non_rebreather' => '#f59e0b',
        'high_flow_mask' => '#fb923c',
        'hfnc' => '#f97316',
        'cpap' => '#f43f5e',
        'bipap' => '#e11d48',
        'tracheostomy' => '#a78bfa',
        'ventilator' => '#dc2626',
        'other' => '#64748b',
    ];

    $duration = fn (int $minutes) => \App\Support\OxygenTherapyChart::durationLabel($minutes);
    $since = fn ($time) => $time->isToday() ? $time->format('H:i') : $time->format('j M H:i');

    // A form that failed validation opens again with what was entered
    $failedForm = old('active_tab') === 'oxygen' ? old('_form') : null;
    $changeFailed = $failedForm === 'change';
    $voidFailed = $failedForm && str_starts_with($failedForm, 'void-') ? (int) substr($failedForm, 5) : null;

    // The change form starts from the oxygen now, so a flow or FiO2 change is one tap
    $changeState = [
        'delivery' => $changeFailed ? (string) old('oxygen_delivery', '') : ($onOxygen ? $current['delivery'] : 'nasal_cannula'),
        'flow' => $changeFailed ? (string) old('oxygen_flow_rate', '') : ($onOxygen && $current['flow'] !== null ? $otc::formatFlowRate($current['flow']) : ''),
        'fio2' => $changeFailed ? (string) old('fio2_percent', '') : (string) ($onOxygen ? ($current['fio2'] ?? '') : ''),
        'tmin' => $changeFailed ? (string) old('target_spo2_min', '') : (string) ($currentTarget[0] ?? ''),
        'tmax' => $changeFailed ? (string) old('target_spo2_max', '') : (string) ($currentTarget[1] ?? ''),
        'earlier' => $changeFailed && filled(old('started_at')),
        'at' => $changeFailed ? (string) old('started_at', '') : '',
    ];

    $panel = [
        'tabId' => 'oxygen',
        'panel' => $changeFailed ? 'change' : null,
        'striking' => $voidFailed,
        'confirmRoomAir' => false,
        'range' => $chart['range'],
        'chartMissing' => false,
        'guide' => $otc::DEVICE_GUIDE,
        'chart' => $chart + ['colors' => $deviceColors],
    ] + $changeState;

    $fieldClass = 'block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-sky-500 focus:ring-sky-500';
    $labelClass = 'block text-xs font-semibold text-gray-700 mb-1';
    $chipClass = 'px-2 py-1 rounded-md border text-xs font-semibold transition-colors';
@endphp

@once
    <script>
        // Patient Details > Oxygen Therapy: the change form and the progression charts
        window.oxygenTherapyPanel = function (config) {
            // Chart.js instances stay out of Alpine's reactive state
            const charts = {};
            const data = config.chart;
            const HOUR = 3600000;
            const RANGES = { '24h': 24, '72h': 72, '7d': 168, all: null };
            // Times arrive as the hospital's wall clock in "UTC" milliseconds, so every browser shows ward time
            const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const pad = n => String(n).padStart(2, '0');
            const clock = ms => { const d = new Date(ms); return pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()); };
            const day = ms => { const d = new Date(ms); return d.getUTCDate() + ' ' + MONTHS[d.getUTCMonth()]; };
            const stepFor = span => HOUR * (span <= 7 * HOUR ? 1 : span <= 14 * HOUR ? 2 : span <= 30 * HOUR ? 3 : span <= 84 * HOUR ? 12 : 24);
            let labelSpan = 0;
            const tickLabel = ms => {
                if (labelSpan > 30 * HOUR) return [day(ms), clock(ms)];
                return clock(ms) === '00:00' ? day(ms) : clock(ms);
            };
            const colorOf = device => data.colors[device] || '#64748b';
            const tint = (hex, alpha) => hex + Math.round(alpha * 255).toString(16).padStart(2, '0');
            // The same axis widths on both charts keep their time axes lined up
            const fixWidth = scale => { scale.width = 48; };

            // Each setting holds from its start until the next one starts, or until now
            const segments = data.steps.map((step, i) => ({ ...step, end: i + 1 < data.steps.length ? data.steps[i + 1].t : data.now }));
            const visible = (seg, w) => seg.end >= w.min && seg.t <= w.max;
            const across = (x, area, seg) => [Math.max(x.getPixelForValue(seg.t), area.left), Math.min(x.getPixelForValue(seg.end), area.right)];

            // Shade the time on each device, and name it in the strip above the plot
            const deviceBands = {
                id: 'oxygenDevices',
                beforeDatasetsDraw(chart) {
                    const { ctx, chartArea: area, scales: { x } } = chart;
                    ctx.save();
                    ctx.font = '600 10px sans-serif';
                    ctx.textBaseline = 'bottom';
                    segments.forEach(seg => {
                        const [left, right] = across(x, area, seg);
                        if (right - left < 1) return;
                        ctx.fillStyle = tint(colorOf(seg.device), 0.14);
                        ctx.fillRect(left, area.top, right - left, area.bottom - area.top);
                        ctx.fillStyle = colorOf(seg.device);
                        ctx.fillRect(left, area.top - 3, right - left, 3);
                        if (ctx.measureText(seg.abbr).width + 4 <= right - left) {
                            ctx.fillText(seg.abbr, left + 2, area.top - 5);
                        }
                    });
                    ctx.restore();
                },
            };

            // The SpO2 target in force, shaded where it applied
            const targetBands = {
                id: 'oxygenTargets',
                beforeDatasetsDraw(chart) {
                    const { ctx, chartArea: area, scales: { x, y } } = chart;
                    ctx.save();
                    segments.forEach(seg => {
                        if (!seg.target) return;
                        const [left, right] = across(x, area, seg);
                        const top = Math.max(y.getPixelForValue(seg.target[1]), area.top);
                        const bottom = Math.min(y.getPixelForValue(seg.target[0]), area.bottom);
                        if (right - left < 1 || bottom <= top) return;
                        ctx.fillStyle = 'rgba(16, 185, 129, 0.13)';
                        ctx.fillRect(left, top, right - left, bottom - top);
                        ctx.strokeStyle = 'rgba(5, 150, 105, 0.55)';
                        ctx.setLineDash([4, 3]);
                        ctx.beginPath();
                        ctx.moveTo(left, top); ctx.lineTo(right, top);
                        ctx.moveTo(left, bottom); ctx.lineTo(right, bottom);
                        ctx.stroke();
                    });
                    ctx.restore();
                },
            };

            // A setting as a flat line over its time: [start, end] pairs, a gap where it has no value
            const holds = key => {
                const points = [];
                segments.forEach((seg, i) => {
                    if (seg[key] === null || seg[key] === undefined) {
                        points.push({ x: seg.t, y: null, s: i });
                        return;
                    }
                    points.push({ x: seg.t, y: seg[key], s: i, start: true }, { x: seg.end, y: seg[key], s: i });
                });
                return points;
            };

            return {
                ...config,

                init() {
                    if (this.activeTab === this.tabId) this.$nextTick(() => this.drawCharts());
                    this.$watch('activeTab', value => {
                        if (value === this.tabId) this.$nextTick(() => this.drawCharts());
                    });
                },

                // The change form
                get onOxygen() {
                    return this.delivery !== '' && this.delivery !== 'room_air';
                },
                get deviceGuide() {
                    return this.guide[this.delivery] || { flow: [], fio2: [], hint: '' };
                },
                toggle(name) {
                    this.panel = this.panel === name ? null : name;
                    this.confirmRoomAir = false;
                },
                setTarget(min, max) {
                    this.tmin = String(min);
                    this.tmax = String(max);
                },
                localNow() {
                    const d = new Date();
                    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
                },
                useEarlier() {
                    this.earlier = true;
                    if (!this.at) this.at = this.localNow();
                },

                // The progression
                timeWindow() {
                    const times = data.steps.map(s => s.t).concat(data.spo2.map(p => p.t));
                    const hours = RANGES[this.range];
                    const last = Math.max(data.now, ...times);
                    const first = hours ? last - hours * HOUR : Math.min(...times);
                    const padding = Math.max((last - first) * 0.02, 10 * 60000);
                    const min = hours ? first : first - padding;
                    // The start snaps back to a whole step; the end stays just past now
                    const max = last + padding;
                    const span = max - min, step = stepFor(span);
                    return { min: Math.floor(min / step) * step, max, step, span };
                },
                get spo2Visible() {
                    const w = this.timeWindow();
                    return data.spo2.filter(p => p.t >= w.min && p.t <= w.max).length;
                },
                get stepsVisible() {
                    const w = this.timeWindow();
                    return segments.filter(seg => visible(seg, w)).length;
                },
                setRange(range) {
                    this.range = range;
                    this.drawCharts();
                },
                drawCharts() {
                    if (!data.steps.length && !data.spo2.length) return;
                    if (typeof Chart === 'undefined') {
                        this.chartMissing = true;
                        return;
                    }
                    const w = this.timeWindow();
                    labelSpan = w.span;
                    ['spo2', 'support'].forEach(key => {
                        const canvas = this.$refs[key + 'Chart'];
                        if (!canvas) return;
                        if (charts[key]) {
                            Object.assign(charts[key].options.scales.x, { min: w.min, max: w.max });
                            charts[key].options.scales.x.ticks.stepSize = w.step;
                            charts[key].update('none');
                            return;
                        }
                        charts[key] = new Chart(canvas, key === 'spo2' ? this.spo2Config(w) : this.supportConfig(w));
                    });
                },
                timeAxis(w) {
                    return {
                        type: 'linear',
                        min: w.min,
                        max: w.max,
                        grid: { color: '#f3f4f6' },
                        // Ticks on whole steps whatever the ends are, so the axis can stop just past now
                        afterBuildTicks: axis => {
                            const step = axis.options.ticks.stepSize, ticks = [];
                            for (let t = Math.ceil(axis.min / step) * step; t <= axis.max; t += step) ticks.push({ value: t });
                            axis.ticks = ticks;
                        },
                        ticks: {
                            stepSize: w.step,
                            maxTicksLimit: 12,
                            maxRotation: 0,
                            color: '#6b7280',
                            font: { size: 11 },
                            callback: value => tickLabel(value),
                        },
                    };
                },
                spo2Config(w) {
                    // Low enough to show the lowest reading and every target band
                    const lows = data.spo2.map(p => p.y).concat(segments.filter(s => s.target).map(s => s.target[0]));
                    const bottom = Math.max(50, Math.floor(Math.min(90, ...lows) - 2));
                    const stateColor = { below: '#dc2626', above: '#f59e0b' };

                    return {
                        type: 'line',
                        data: {
                            datasets: [{
                                label: 'SpO₂',
                                data: data.spo2.map(p => ({ x: p.t, y: p.y, on: p.on, state: p.state })),
                                borderColor: '#2563eb',
                                backgroundColor: '#2563eb',
                                borderWidth: 2,
                                tension: 0.25,
                                pointRadius: c => c.raw && c.raw.state ? 4.5 : 3,
                                pointHoverRadius: 6,
                                pointBackgroundColor: c => (c.raw && stateColor[c.raw.state]) || '#2563eb',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 1,
                            }],
                        },
                        plugins: [targetBands],
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            interaction: { mode: 'nearest', axis: 'x', intersect: false },
                            scales: {
                                x: this.timeAxis(w),
                                y: {
                                    position: 'left',
                                    min: bottom,
                                    max: 100,
                                    title: { display: true, text: 'SpO₂ %', color: '#6b7280', font: { size: 11 } },
                                    ticks: { color: '#6b7280', font: { size: 11 } },
                                    grid: { color: '#f3f4f6' },
                                    afterFit: fixWidth,
                                },
                                y1: {
                                    position: 'right',
                                    ticks: { display: false },
                                    grid: { display: false },
                                    border: { display: false },
                                    afterFit: fixWidth,
                                },
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: items => items.length ? day(items[0].raw.x) + ' ' + clock(items[0].raw.x) : '',
                                        label: item => ' SpO₂ ' + item.raw.y + '%' + (item.raw.on ? ' on ' + item.raw.on : ''),
                                        afterLabel: item => ({ below: ' Below the target', above: ' Above the target while on oxygen' })[item.raw.state] || '',
                                    },
                                },
                            },
                        },
                    };
                },
                supportConfig(w) {
                    const line = (label, key, axis, color, dash) => ({
                        label,
                        key,
                        yAxisID: axis,
                        data: holds(key),
                        borderColor: color,
                        backgroundColor: color,
                        borderWidth: 2.5,
                        borderDash: dash,
                        tension: 0,
                        spanGaps: false,
                        pointRadius: c => c.raw && c.raw.start ? 3.5 : 0,
                        pointHoverRadius: 5,
                    });
                    const flowMax = Math.max(6, ...segments.map(s => s.flow || 0));

                    return {
                        type: 'line',
                        data: {
                            datasets: [
                                line('Flow (L/min)', 'flow', 'y', '#0369a1', []),
                                line('FiO₂ (%)', 'fio2', 'y1', '#047857', [6, 3]),
                            ],
                        },
                        plugins: [deviceBands],
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            // Room above the plot for the device names
                            layout: { padding: { top: 16 } },
                            interaction: { mode: 'nearest', axis: 'x', intersect: false },
                            scales: {
                                x: this.timeAxis(w),
                                y: {
                                    position: 'left',
                                    min: 0,
                                    suggestedMax: flowMax,
                                    title: { display: true, text: 'L/min', color: '#6b7280', font: { size: 11 } },
                                    ticks: { color: '#6b7280', font: { size: 11 } },
                                    grid: { color: '#f3f4f6' },
                                    afterFit: fixWidth,
                                },
                                y1: {
                                    position: 'right',
                                    min: 20,
                                    max: 100,
                                    title: { display: true, text: 'FiO₂ %', color: '#6b7280', font: { size: 11 } },
                                    ticks: { color: '#6b7280', font: { size: 11 } },
                                    grid: { drawOnChartArea: false },
                                    afterFit: fixWidth,
                                },
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, font: { size: 11 } },
                                },
                                tooltip: {
                                    filter: item => item.raw && item.raw.y !== null,
                                    callbacks: {
                                        title: items => items.length ? segments[items[0].raw.s].label : '',
                                        label: item => ' ' + (item.dataset.key === 'flow'
                                            ? 'Flow ' + (Math.round(item.raw.y * 10) / 10) + ' L/min'
                                            : 'FiO₂ ' + item.raw.y + '%'),
                                        footer: items => {
                                            if (!items.length) return '';
                                            const seg = segments[items[0].raw.s];
                                            return 'From ' + day(seg.t) + ' ' + clock(seg.t)
                                                + (seg.target ? '\nSpO₂ target ' + seg.target[0] + '–' + seg.target[1] + '%' : '');
                                        },
                                    },
                                },
                            },
                        },
                    };
                },
            };
        };
    </script>
@endonce

<div x-data="oxygenTherapyPanel(@js($panel))">
    {{-- Header: title, flags, and the two actions --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2" />
                </svg>
                Oxygen Therapy
            </h3>
            @if ($onOxygen)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-sky-600 text-white">On O₂</span>
            @elseif ($current)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Room air</span>
            @endif
            @if ($spo2State === 'below')
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">SpO₂ below target</span>
            @elseif ($spo2State === 'above')
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">SpO₂ above target</span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="toggle('change')"
                class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-semibold shadow-sm transition-colors"
                :class="panel === 'change' ? 'bg-sky-700 text-white' : 'bg-sky-600 text-white hover:bg-sky-700'">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Change oxygen
            </button>
            @if (!$current || $onOxygen)
                <button type="button" @click="confirmRoomAir = !confirmRoomAir; panel = null"
                    class="inline-flex items-center px-3 py-1.5 rounded-md border border-emerald-300 bg-white text-emerald-800 text-sm font-semibold hover:bg-emerald-50">
                    Change to Room Air
                </button>
            @endif
        </div>
    </div>

    {{-- Room air in one step, once confirmed --}}
    @if (!$current || $onOxygen)
        <form x-show="confirmRoomAir" x-cloak method="POST" action="{{ route('ward.oxygen-therapy.store') }}"
            class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2">
            @csrf
            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
            <input type="hidden" name="active_tab" value="oxygen">
            <input type="hidden" name="_form" value="room_air">
            <input type="hidden" name="oxygen_delivery" value="{{ \App\Models\VitalSign::OXYGEN_ROOM_AIR }}">
            @if ($currentTarget)
                <input type="hidden" name="target_spo2_min" value="{{ $currentTarget[0] }}">
                <input type="hidden" name="target_spo2_max" value="{{ $currentTarget[1] }}">
            @endif
            <div class="text-sm text-emerald-900">
                <span class="font-semibold">Change to room air now?</span>
                @if ($current)
                    Stops {{ $current['label'] }}{{ $current['settings'] ? ' ' . $current['settings'] : '' }}.
                @endif
                @if ($currentTarget)
                    <span class="text-emerald-800">The SpO₂ target {{ $otc::targetLabel($currentTarget) }} stays.</span>
                @endif
            </div>
            <div class="flex items-center gap-2 ml-auto">
                <button type="button" @click="confirmRoomAir = false"
                    class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-sm font-semibold hover:bg-gray-50">Cancel</button>
                <button type="submit"
                    class="px-3 py-1.5 rounded-md bg-emerald-600 text-white text-sm font-semibold shadow-sm hover:bg-emerald-700">Yes, room air</button>
            </div>
        </form>
    @endif

    {{-- Why the last form was not saved --}}
    @if ($failedForm && $errors->any())
        <div class="mb-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-800">
            <div class="font-semibold">Not saved</div>
            <ul class="mt-0.5 list-disc ml-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Change the oxygen: starts from what the patient is on now --}}
    <form x-show="panel === 'change'" x-cloak method="POST" action="{{ route('ward.oxygen-therapy.store') }}"
        class="mb-4 rounded-xl border border-sky-200 bg-sky-50/40 p-4">
        @csrf
        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
        <input type="hidden" name="active_tab" value="oxygen">
        <input type="hidden" name="_form" value="change">

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-4">
                <label for="oxygen_delivery" class="{{ $labelClass }}">Delivery <span class="text-red-500">*</span></label>
                <select id="oxygen_delivery" name="oxygen_delivery" x-model="delivery" required class="{{ $fieldClass }}">
                    @foreach ($deliveryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-gray-500" x-show="onOxygen" x-text="deviceGuide.hint"></p>
                <p class="mt-1 text-[11px] text-gray-500" x-show="!onOxygen" x-cloak>No supplemental oxygen: no flow rate or FiO₂.</p>
            </div>
            <div class="md:col-span-2">
                <label for="oxygen_flow_rate" class="{{ $labelClass }}">Flow (L/min)</label>
                <input type="number" id="oxygen_flow_rate" name="oxygen_flow_rate" x-model="flow" inputmode="decimal"
                    step="0.1" min="{{ $otc::FLOW_MIN }}" max="{{ $otc::FLOW_MAX }}" placeholder="L/min"
                    :disabled="!onOxygen" :class="onOxygen ? '' : 'bg-gray-100 cursor-not-allowed'" class="{{ $fieldClass }}">
                <div class="mt-1.5 flex flex-wrap gap-1" x-show="onOxygen">
                    <template x-for="value in deviceGuide.flow" :key="'flow' + value">
                        <button type="button" @click="flow = String(value)" x-text="value + ' L'" class="{{ $chipClass }}"
                            :class="String(flow) === String(value) ? 'bg-sky-700 border-sky-700 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'"></button>
                    </template>
                </div>
            </div>
            <div class="md:col-span-2">
                <label for="fio2_percent" class="{{ $labelClass }}">FiO₂ (%)</label>
                <input type="number" id="fio2_percent" name="fio2_percent" x-model="fio2" inputmode="numeric"
                    step="1" min="{{ $otc::FIO2_MIN }}" max="{{ $otc::FIO2_MAX }}" placeholder="%"
                    :disabled="!onOxygen" :class="onOxygen ? '' : 'bg-gray-100 cursor-not-allowed'" class="{{ $fieldClass }}">
                <div class="mt-1.5 flex flex-wrap gap-1" x-show="onOxygen">
                    <template x-for="value in deviceGuide.fio2" :key="'fio2' + value">
                        <button type="button" @click="fio2 = String(value)" x-text="value + '%'" class="{{ $chipClass }}"
                            :class="String(fio2) === String(value) ? 'bg-emerald-700 border-emerald-700 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'"></button>
                    </template>
                </div>
            </div>
            <div class="md:col-span-4">
                <label for="target_spo2_min" class="{{ $labelClass }}">SpO₂ target (%)</label>
                <div class="flex items-center gap-2">
                    <input type="number" id="target_spo2_min" name="target_spo2_min" x-model="tmin" inputmode="numeric"
                        min="{{ $otc::TARGET_MIN }}" max="{{ $otc::TARGET_MAX }}" placeholder="from" aria-label="Target from"
                        class="{{ $fieldClass }}">
                    <span class="text-xs text-gray-500">to</span>
                    <input type="number" name="target_spo2_max" x-model="tmax" inputmode="numeric"
                        min="{{ $otc::TARGET_MIN }}" max="{{ $otc::TARGET_MAX }}" placeholder="to" aria-label="Target to"
                        class="{{ $fieldClass }}">
                </div>
                <div class="mt-1.5 flex flex-wrap gap-1">
                    @foreach ($otc::TARGET_PRESETS as $preset)
                        <button type="button" @click="setTarget({{ $preset['min'] }}, {{ $preset['max'] }})" title="{{ $preset['label'] }}"
                            class="{{ $chipClass }}"
                            :class="tmin === '{{ $preset['min'] }}' && tmax === '{{ $preset['max'] }}' ? 'bg-emerald-700 border-emerald-700 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'">
                            {{ $preset['min'] }}–{{ $preset['max'] }}%
                        </button>
                    @endforeach
                    <button type="button" @click="setTarget('', '')" class="{{ $chipClass }} bg-white border-gray-300 text-gray-500 hover:bg-gray-50">None</button>
                </div>
            </div>
        </div>

        <div class="mt-3 grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-4">
                <label class="{{ $labelClass }}">Started</label>
                <div x-show="!earlier" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm py-2">
                    <span class="font-semibold text-gray-800">Now</span>
                    <button type="button" @click="useEarlier()" class="text-xs font-semibold text-blue-700 hover:underline">
                        Earlier time
                    </button>
                </div>
                <div x-show="earlier" x-cloak class="flex items-center gap-2">
                    <input type="datetime-local" name="started_at" x-model="at"
                        :disabled="!earlier" :required="earlier" class="{{ $fieldClass }}">
                    <button type="button" @click="earlier = false; at = ''"
                        class="shrink-0 text-xs font-semibold text-blue-700 hover:underline">Now</button>
                </div>
            </div>
            <div class="md:col-span-8">
                <label for="oxygen_notes" class="{{ $labelClass }}">Notes</label>
                <input type="text" id="oxygen_notes" name="notes" maxlength="255" value="{{ $changeFailed ? old('notes') : '' }}"
                    placeholder="e.g. weaned as SpO₂ stable, per doctor's order" class="{{ $fieldClass }}">
            </div>
        </div>

        <div class="mt-3 flex items-center justify-end gap-2">
            <button type="button" @click="panel = null"
                class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-sm font-semibold hover:bg-gray-50">Cancel</button>
            <button type="submit"
                class="inline-flex items-center px-5 py-2 rounded-md bg-sky-600 text-white text-sm font-semibold shadow-sm hover:bg-sky-700">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Save change
            </button>
        </div>
    </form>

    {{-- At a glance --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        {{-- What the patient is on now --}}
        <div class="rounded-xl border p-3 {{ $onOxygen ? 'border-sky-300 bg-sky-50/60' : 'border-gray-200 bg-white' }}">
            <div class="text-[11px] font-bold uppercase tracking-wide text-sky-700">Oxygen now</div>
            @if ($current)
                <div class="mt-1 flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $deviceColors[$current['delivery']] ?? '#64748b' }}"></span>
                    <span class="text-sm font-bold text-gray-900">{{ $current['label'] }}</span>
                </div>
                <div class="mt-0.5 text-lg font-bold {{ $onOxygen ? 'text-sky-800' : 'text-gray-600' }}">{{ $current['settings'] ?? 'Settings not recorded' }}</div>
                <div class="mt-1 text-[11px] text-gray-500">
                    Since {{ $since($current['at']) }} ({{ $duration($current['minutes']) }})
                </div>
                <div class="text-[11px] text-gray-500">
                    {{ $current['source'] === 'therapy' ? 'Set here' : 'From vital signs' }}{{ $current['by'] ? ' by ' . $current['by'] : '' }}
                </div>
            @else
                <div class="mt-1 text-sm text-gray-500">Not recorded this admission</div>
                <div class="mt-1 text-[11px] text-gray-400">Use Change oxygen, or record it with the vital signs.</div>
            @endif
        </div>

        {{-- The SpO2 aimed for --}}
        <div class="rounded-xl border border-gray-200 bg-white p-3">
            <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">SpO₂ target</div>
            @if ($currentTarget)
                <div class="mt-1 text-2xl font-bold text-gray-900">{{ $otc::targetLabel($currentTarget) }}</div>
                @foreach ($otc::TARGET_PRESETS as $preset)
                    @if ($preset['min'] === $currentTarget[0] && $preset['max'] === $currentTarget[1])
                        <div class="mt-1 text-[11px] text-gray-500">{{ $preset['label'] }}</div>
                    @endif
                @endforeach
            @else
                <div class="mt-1 text-sm text-gray-500">Not set</div>
                <div class="mt-1 text-[11px] text-gray-400">Set it with Change oxygen.</div>
            @endif
        </div>

        {{-- The latest SpO2, against the target --}}
        @php
            $spo2Tone = match ($spo2State) {
                'below' => 'border-red-300 bg-red-50/60',
                'above' => 'border-amber-300 bg-amber-50/60',
                default => 'border-gray-200 bg-white',
            };
        @endphp
        <div class="rounded-xl border p-3 {{ $spo2Tone }}">
            <div class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Latest SpO₂</div>
            @if ($latestSpo2)
                <div class="mt-1 text-2xl font-bold {{ $spo2State === 'below' ? 'text-red-700' : 'text-gray-900' }}">
                    {{ $latestSpo2['spo2'] }}<span class="text-sm font-medium text-gray-500">%</span>
                    @if ($spo2State === 'below')
                        <span class="ml-1 align-middle inline-flex px-1.5 rounded bg-red-600 text-white text-[11px] font-semibold">Below target</span>
                    @elseif ($spo2State === 'above')
                        <span class="ml-1 align-middle inline-flex px-1.5 rounded bg-amber-200 text-amber-900 text-[11px] font-semibold">Above target on O₂</span>
                    @elseif (!$latestSpo2['stale'] && ($latestSpo2['setting']['target'] ?? null))
                        <span class="ml-1 align-middle inline-flex px-1.5 rounded bg-emerald-100 text-emerald-800 text-[11px] font-semibold">On target</span>
                    @endif
                </div>
                <div class="mt-1 text-[11px] text-gray-500">
                    {{ $since($latestSpo2['at']) }}{{ ($latestSpo2['setting']['short'] ?? null) ? ' on ' . $latestSpo2['setting']['short'] : '' }}
                </div>
                @if ($latestSpo2['stale'])
                    <div class="mt-0.5 text-[11px] font-semibold text-amber-700">Taken before the change at {{ $since($current['at']) }}</div>
                @endif
            @else
                <div class="mt-1 text-sm text-gray-500">No SpO₂ recorded</div>
                <div class="mt-1 text-[11px] text-gray-400">SpO₂ comes from the vital signs.</div>
            @endif
        </div>

        {{-- How long on supplemental oxygen --}}
        <div class="rounded-xl border border-gray-200 bg-white p-3">
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-600">Time on oxygen</div>
            @if ($oxygen['on_oxygen_since'])
                <div class="mt-1 text-2xl font-bold text-gray-900">{{ $duration((int) round(abs($oxygen['on_oxygen_since']->diffInMinutes(now())))) }}</div>
                <div class="mt-1 text-[11px] text-gray-500">Unbroken since {{ $since($oxygen['on_oxygen_since']) }}</div>
            @elseif ($current)
                <div class="mt-1 text-sm font-semibold text-gray-700">Off oxygen</div>
                <div class="mt-1 text-[11px] text-gray-500">On room air since {{ $since($current['at']) }}</div>
            @else
                <div class="mt-1 text-sm text-gray-500">&mdash;</div>
            @endif
        </div>
    </div>

    {{-- How the oxygen and SpO2 have moved this admission --}}
    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Progression this admission</h4>
            @if ($hasChart)
                <div class="inline-flex rounded-md border border-gray-200 overflow-hidden text-xs" role="group" aria-label="Time range">
                    @foreach (['24h' => '24h', '72h' => '72h', '7d' => '7 days', 'all' => 'All'] as $key => $label)
                        <button type="button" @click="setRange('{{ $key }}')" :aria-pressed="range === '{{ $key }}'"
                            :class="range === '{{ $key }}' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            class="px-2.5 py-1 font-semibold {{ $loop->last ? '' : 'border-r border-gray-200' }}">{{ $label }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($hasChart)
            <p x-show="chartMissing" x-cloak class="mt-3 text-sm text-gray-500">
                The chart could not be drawn because the chart library did not load. The changes are listed below.
            </p>
            <div class="mt-3">
                <div class="text-xs font-semibold text-gray-600">SpO₂ from the vital signs<span class="font-normal text-gray-400"> &middot; green band: the target</span></div>
                <div class="relative h-44">
                    <canvas x-ref="spo2Chart"></canvas>
                    <div x-show="spo2Visible === 0" x-cloak
                        class="absolute inset-0 flex items-center justify-center rounded-lg bg-white/80 text-sm text-gray-500">
                        No SpO₂ recorded in this period
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-xs font-semibold text-gray-600">Oxygen given<span class="font-normal text-gray-400"> &middot; shaded by device</span></div>
                <div class="relative h-52">
                    <canvas x-ref="supportChart"></canvas>
                    <div x-show="stepsVisible === 0" x-cloak
                        class="absolute inset-0 flex items-center justify-center rounded-lg bg-white/80 text-sm text-gray-500">
                        No oxygen recorded in this period
                    </div>
                </div>
            </div>
            <p class="mt-2 text-[11px] text-gray-500">
                Room air counts as 0 L/min at 21%. SpO₂ points turn red below the target, and amber above it while on oxygen.
            </p>
        @else
            <div class="mt-3 rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center">
                <p class="text-sm font-medium text-gray-500">The chart appears once oxygen or SpO₂ is recorded</p>
                <p class="mt-1 text-xs text-gray-400">Changes made here and the oxygen and SpO₂ recorded with vital signs are charted together.</p>
            </div>
        @endif
    </div>

    {{-- Every setting this admission, newest first --}}
    <div class="rounded-xl border border-gray-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-100">
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Changes this admission</h4>
            <span class="text-[11px] text-gray-500">Made here, or recorded with vital signs when the oxygen differed</span>
        </div>
        @if ($oxygen['history'])
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Started</th>
                            <th class="px-3 py-2 text-left">Oxygen</th>
                            <th class="px-3 py-2 text-left">SpO₂ target</th>
                            <th class="px-3 py-2 text-left">Lasted</th>
                            <th class="px-3 py-2 text-left">Recorded</th>
                            <th class="px-3 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($oxygen['history'] as $entry)
                            @php
                                $isCurrent = $current && $entry['key'] === $current['key'];
                                $canStrike = $entry['source'] === 'therapy' && !$entry['voided'];
                            @endphp
                            <tr class="{{ $entry['voided'] ? 'bg-gray-50 text-gray-400' : ($isCurrent ? 'bg-sky-50/60' : '') }}">
                                <td class="px-3 py-2 whitespace-nowrap align-top">{{ $entry['at']->format('j M H:i') }}</td>
                                <td class="px-3 py-2 align-top">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                                            style="background: {{ $entry['voided'] ? '#d1d5db' : ($deviceColors[$entry['delivery']] ?? '#64748b') }}"></span>
                                        <span class="font-semibold {{ $entry['voided'] ? 'line-through' : 'text-gray-900' }}">{{ $entry['label'] }}</span>
                                        @if ($entry['on_oxygen'] && $entry['settings'])
                                            <span class="{{ $entry['voided'] ? 'line-through' : 'text-gray-700' }}">{{ $entry['settings'] }}</span>
                                        @endif
                                        @if ($isCurrent)
                                            <span class="inline-flex px-1.5 rounded bg-sky-600 text-white text-[10px] font-bold uppercase">Now</span>
                                        @endif
                                    </div>
                                    @if ($entry['notes'])
                                        <div class="mt-0.5 text-xs text-gray-500">{{ $entry['notes'] }}</div>
                                    @endif
                                    @if ($entry['voided'])
                                        <div class="mt-0.5 text-xs text-red-700">
                                            Struck out {{ $entry['record']->voided_at->format('j M H:i') }}{{ $entry['record']->voidedBy ? ' by ' . $entry['record']->voidedBy->name : '' }}: {{ $entry['record']->void_reason }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap align-top">{{ $otc::targetLabel($entry['target']) ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap align-top">
                                    @if (!$entry['voided'])
                                        {{ $duration($entry['minutes']) }}{{ $entry['until'] ? '' : ' so far' }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 align-top text-xs">
                                    <div class="{{ $entry['voided'] ? '' : 'text-gray-700' }}">
                                        {{ $entry['source'] === 'therapy' ? 'Oxygen Therapy' : 'Vital signs' }}{{ $entry['spo2'] !== null ? ' (SpO₂ ' . $entry['spo2'] . '%)' : '' }}
                                    </div>
                                    @if ($entry['by'])
                                        <div class="text-gray-500">{{ $entry['by'] }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right align-top whitespace-nowrap">
                                    @if ($canStrike)
                                        <button type="button" @click="striking = striking === {{ $entry['id'] }} ? null : {{ $entry['id'] }}"
                                            class="text-xs font-semibold text-red-700 hover:underline">Strike out</button>
                                    @endif
                                </td>
                            </tr>
                            @if ($canStrike)
                                <tr x-show="striking === {{ $entry['id'] }}" x-cloak>
                                    <td colspan="6" class="px-3 py-2 bg-red-50">
                                        <form method="POST" action="{{ route('ward.oxygen-therapy.void', $entry['record']) }}"
                                            class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="active_tab" value="oxygen">
                                            <input type="hidden" name="_form" value="void-{{ $entry['id'] }}">
                                            <input type="text" name="void_reason" required maxlength="255"
                                                value="{{ $voidFailed === $entry['id'] ? old('void_reason') : '' }}"
                                                placeholder="Why this change was wrong, e.g. wrong patient"
                                                class="flex-1 min-w-[12rem] rounded-md border-gray-300 text-sm focus:border-red-500 focus:ring-red-500">
                                            <button type="button" @click="striking = null"
                                                class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-xs font-semibold hover:bg-gray-50">Cancel</button>
                                            <button type="submit"
                                                class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700">Strike out</button>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-4 py-6 text-center">
                <p class="text-sm text-gray-500">No oxygen recorded this admission</p>
                <p class="mt-1 text-xs text-gray-400">Changes made here, and oxygen recorded with the vital signs, are listed here.</p>
            </div>
        @endif
    </div>
</div>
