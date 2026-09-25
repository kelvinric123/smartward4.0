{{--
    Patient details panel for a clinical indicator recorded as monitor readings
    (the hemodynamic numerics) rather than scored. Top to bottom: the latest
    value of each parameter, how they have moved this admission, the form to
    record new readings, and the readings themselves.

    The parameters and ranges come from the library definition, which is what
    the Ward Types page shows; the server flags the readings again when they
    are saved (ClinicalIndicatorReadings). Chart.js is loaded by patient-details.
--}}
@php
    $readings = \App\Support\ClinicalIndicatorReadings::class;
    $definition = $indicator['definition'];
    $items = $definition['items'];
    $trend = $indicator['trend'] ?? collect();
    $latest = $trend->last();
    $flags = $readings::FLAGS;
    $groupMembers = $readings::groupMembers($definition);

    $chipClasses = [
        'high' => 'bg-red-100 text-red-800 border-red-200',
        'moderate' => 'bg-amber-100 text-amber-800 border-amber-200',
        'low' => 'bg-green-100 text-green-800 border-green-200',
    ];
    $tileValueClasses = ['high' => 'text-red-600', 'moderate' => 'text-amber-600', 'low' => 'text-gray-900'];
    $cellClasses = ['high' => 'text-red-700 font-bold', 'moderate' => 'text-amber-700 font-semibold', 'low' => 'text-gray-800'];

    // Monitor colours: arterial red, central venous blue, cardiac output green
    $colours = ['SBP' => '#dc2626', 'DBP' => '#dc2626', 'MAP' => '#991b1b', 'CVP' => '#2563eb', 'CO' => '#0e7490', 'CI' => '#059669'];
    $palette = ['#7c3aed', '#db2777', '#ca8a04', '#4b5563'];
    foreach ($items as $i => $item) {
        $colours[$item['abbr']] ??= $palette[$i % count($palette)];
    }
    $translucent = fn (string $hex, float $alpha) => 'rgba(' . implode(', ', array_map('hexdec', str_split(ltrim($hex, '#'), 2))) . ', ' . $alpha . ')';
    $range = fn (array $item) => $readings::format($item['normal'][0], $item['decimals'] ?? 0) . '–' . $readings::format($item['normal'][1], $item['decimals'] ?? 0);
    $when = fn ($at) => $at->isToday() ? $at->format('H:i') : $at->format('d M H:i');
    // A tile's format with each {ABBR} filled in, e.g. 90–140/60–90 (70–105)
    $fillTokens = fn (array $tokens, callable $fill) => collect($tokens)
        ->map(fn ($token) => preg_match('/^\{([A-Za-z0-9]+)\}$/', $token, $match) ? $fill($match[1]) : $token)
        ->implode('');

    // Latest and previous value of each parameter this admission. Not every
    // parameter is recorded every time (cardiac output less often, say).
    $lastFor = [];
    $prevFor = [];
    foreach ($trend->reverse() as $record) {
        foreach ($record->item_scores ?? [] as $entry) {
            if (!isset($lastFor[$entry['abbr']])) {
                $lastFor[$entry['abbr']] = $entry + ['at' => $record->recorded_at];
            } elseif (!isset($prevFor[$entry['abbr']])) {
                $prevFor[$entry['abbr']] = $entry + ['at' => $record->recorded_at];
            }
        }
    }

    // A tile's latest values in its format, each coloured by its flag. Built as one string
    // because whitespace between Blade elements would split 104/56 (71) into 104 / 56 ( 71 ).
    $tileValueHtml = fn (array $tokens) => collect($tokens)->map(function ($token) use ($lastFor, $flags, $tileValueClasses, $readings, $definition) {
        if (!preg_match('/^\{([A-Za-z0-9]+)\}$/', $token, $match)) {
            return '<span class="text-gray-400 font-medium">' . e($token) . '</span>';
        }
        $entry = $lastFor[$match[1]] ?? null;
        $class = $entry ? $tileValueClasses[$flags[$entry['flag']]['tone']] : 'text-gray-300';

        return '<span class="' . $class . '" title="' . e($match[1] . ($entry ? ': ' . $entry['label'] : '')) . '">'
            . e($entry ? $readings::display($entry, $definition) : '-') . '</span>';
    })->implode('');

    // One tile per parameter; a group (SBP, DBP and MAP) is one tile in its monitor format, 120/80 (93)
    $itemsByAbbr = collect($items)->keyBy('abbr');
    $tiles = [];
    $tiled = [];
    foreach ($items as $item) {
        if (isset($tiled[$item['abbr']])) {
            continue;
        }
        $group = $item['group'] ?? null;
        $format = $group ? ($definition['groups'][$group]['format'] ?? null) : null;
        $abbrs = $format ? $groupMembers[$group] : [$item['abbr']];
        foreach ($abbrs as $abbr) {
            $tiled[$abbr] = true;
        }
        $tiles[] = [
            'label' => $format ? $group : $item['abbr'],
            'name' => $format ? $definition['groups'][$group]['label'] : $item['name'],
            'unit' => $item['unit'],
            'abbrs' => $abbrs,
            'tokens' => preg_split('/(\{[A-Za-z0-9]+\})/', $format ?: '{' . $item['abbr'] . '}', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY),
        ];
    }

    // The trend: pressures on one chart, cardiac output on another, the value steered by on the left axis
    $toLocalMs = fn ($time) => ($time->getTimestamp() + $time->getOffset()) * 1000;
    $chartOf = fn (array $item) => $item['unit'] === 'mmHg' ? 'pressure' : 'flow';
    $flowItems = collect($items)->filter(fn ($item) => $chartOf($item) === 'flow');
    $leftFlowUnit = ($flowItems->first(fn ($item) => isset($item['trend'])) ?? $flowItems->first())['unit'] ?? null;
    $axisOf = fn (array $item) => $chartOf($item) === 'flow' && $item['unit'] !== $leftFlowUnit ? 'y1' : 'y';

    $series = [];
    $lines = [];
    $shades = [];
    foreach ($items as $item) {
        $abbr = $item['abbr'];
        $members = $groupMembers[$item['group'] ?? ''] ?? [];
        // The second of a group (DBP) is shaded up to the first (SBP): the arterial pressure band
        $shadesToPrevious = count($members) > 1 && ($members[1] ?? null) === $abbr;
        $series[] = [
            'abbr' => $abbr,
            'name' => $item['name'],
            'unit' => $item['unit'],
            'decimals' => $item['decimals'] ?? 0,
            'chart' => $chartOf($item),
            'axis' => $axisOf($item),
            'color' => $colours[$abbr],
            'fill' => $shadesToPrevious ? $translucent($colours[$abbr], 0.08) : null,
            'width' => isset($item['trend']) ? 3 : ($members ? 1.5 : 2),
        ];

        if (isset($item['trend'])) {
            foreach (['escalate_below', 'escalate_above'] as $key) {
                if (isset($item[$key])) {
                    $lines[] = [
                        'chart' => $chartOf($item), 'axis' => $axisOf($item), 'y' => $item[$key], 'color' => '#dc2626',
                        'label' => $abbr . ' ' . $readings::format($item[$key], $item['decimals'] ?? 0),
                    ];
                }
            }
            if ($item['trend'] === 'band') {
                $shades[] = [
                    'chart' => $chartOf($item), 'axis' => $axisOf($item), 'low' => $item['normal'][0], 'high' => $item['normal'][1],
                    'color' => 'rgba(16, 185, 129, 0.10)', 'textColor' => '#047857', 'label' => $abbr . ' normal ' . $range($item),
                ];
            }
        }
    }

    $axes = [];
    foreach (collect($items)->groupBy(fn ($item) => $chartOf($item) . '|' . $axisOf($item)) as $key => $axisItems) {
        [$chart, $axis] = explode('|', $key);
        $lows = $axisItems->map(fn ($item) => min($item['normal'][0], $item['escalate_below'] ?? $item['normal'][0]));
        $axes[] = [
            'chart' => $chart,
            'id' => $axis,
            'title' => $axisItems->count() > 1 ? $axisItems->first()['unit'] : $axisItems->first()['abbr'] . ' (' . $axisItems->first()['unit'] . ')',
            'min' => $chart === 'pressure' ? 0 : floor($lows->min() * 0.6),
            'max' => ceil($axisItems->max(fn ($item) => $item['normal'][1]) * ($chart === 'pressure' ? 1.15 : 1.25)),
        ];
    }

    $points = $trend->map(fn ($record) => [
        't' => $toLocalMs($record->recorded_at),
        'r' => collect($record->item_scores ?? [])
            ->mapWithKeys(fn ($entry) => [$entry['abbr'] => [(float) $entry['value'], $entry['flag'] ?? 'normal']])
            ->all(),
    ])->values()->all();
    $recorded = collect($points)->flatMap(fn ($point) => array_keys($point['r']))->unique();
    $charted = [
        'pressure' => collect($series)->where('chart', 'pressure')->pluck('abbr')->intersect($recorded)->isNotEmpty(),
        'flow' => collect($series)->where('chart', 'flow')->pluck('abbr')->intersect($recorded)->isNotEmpty(),
    ];
    $spanMinutes = $trend->count() > 1 ? abs($trend->last()->recorded_at->diffInMinutes($trend->first()->recorded_at)) : 0;

    $panel = [
        'tabId' => 'indicator-' . $indicator['id'],
        // The form
        'items' => array_map(fn ($item) => [
            'abbr' => $item['abbr'],
            'normal' => $item['normal'],
            'below' => $item['escalate_below'] ?? null,
            'above' => $item['escalate_above'] ?? null,
        ], array_values($items)),
        'values' => array_fill(0, count($items), ''),
        'groups' => $groupMembers,
        'checks' => $definition['checks'] ?? [],
        'bands' => array_values($definition['bands'] ?? []),
        'flags' => $flags,
        'chips' => $chipClasses,
        // The trend
        'points' => $points,
        'now' => $toLocalMs(now()),
        'range' => $spanMinutes > 24 * 60 ? '24h' : 'all',
        'series' => $series,
        'lines' => $lines,
        'shades' => $shades,
        'axes' => $axes,
    ];
@endphp

@once
    <script>
        // One readings panel (form, trend charts) per clinical indicator recorded as monitor readings
        window.clinicalReadingsPanel = function (config) {
            // Chart.js instances stay out of Alpine's reactive state
            const charts = {};
            const HOUR = 3600000;
            const RANGES = { '6h': 6, '12h': 12, '24h': 24, '72h': 72, all: null };
            // Times arrive as the hospital's wall clock in "UTC" milliseconds, so every browser shows ward time
            const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const pad = n => String(n).padStart(2, '0');
            const clock = ms => { const d = new Date(ms); return pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()); };
            const day = ms => { const d = new Date(ms); return d.getUTCDate() + ' ' + MONTHS[d.getUTCMonth()]; };
            // Ticks on whole hours; a tick at midnight names the day, and past a day every tick carries its date
            const stepFor = span => HOUR * (span <= 7 * HOUR ? 1 : span <= 14 * HOUR ? 2 : span <= 30 * HOUR ? 3 : span <= 84 * HOUR ? 12 : 24);
            let labelSpan = 0;
            const tickLabel = ms => {
                if (labelSpan > 30 * HOUR) return [day(ms), clock(ms)];
                return clock(ms) === '00:00' ? day(ms) : clock(ms);
            };
            // Readings to their parameter's decimals, trailing zeros dropped (5.00 -> 5.0), as on the server
            const fmt = (value, decimals) => decimals
                ? Number(value).toFixed(decimals).replace(/(\.\d*?[1-9])0+$|(\.0)0+$/, '$1$2')
                : String(Math.round(value));
            const rank = flag => !flag || flag === 'normal' ? 0 : (flag.indexOf('escalate') === 0 ? 2 : 1);

            // The values the ICU steers by, across the whole chart: normal-range bands and escalation lines
            const references = {
                id: 'readingReferences',
                beforeDatasetsDraw(chart, args, options) {
                    const { ctx, chartArea } = chart;
                    const width = chartArea.right - chartArea.left;
                    ctx.save();
                    ctx.font = '600 10px sans-serif';
                    (options.shades || []).forEach(shade => {
                        const scale = chart.scales[shade.axis];
                        if (!scale) return;
                        const top = Math.max(scale.getPixelForValue(shade.high), chartArea.top);
                        const bottom = Math.min(scale.getPixelForValue(shade.low), chartArea.bottom);
                        if (bottom <= top) return;
                        ctx.fillStyle = shade.color;
                        ctx.fillRect(chartArea.left, top, width, bottom - top);
                        ctx.fillStyle = shade.textColor;
                        ctx.textAlign = 'right';
                        ctx.fillText(shade.label, chartArea.right - 4, top + 11);
                    });
                    (options.lines || []).forEach(line => {
                        const scale = chart.scales[line.axis];
                        if (!scale) return;
                        const y = scale.getPixelForValue(line.y);
                        if (y < chartArea.top || y > chartArea.bottom) return;
                        ctx.strokeStyle = line.color;
                        ctx.lineWidth = 1.5;
                        ctx.setLineDash([6, 4]);
                        ctx.beginPath();
                        ctx.moveTo(chartArea.left, y);
                        ctx.lineTo(chartArea.right, y);
                        ctx.stroke();
                        ctx.setLineDash([]);
                        ctx.fillStyle = line.color;
                        ctx.textAlign = 'left';
                        ctx.fillText(line.label, chartArea.left + 4, y - 4);
                    });
                    ctx.restore();
                },
            };

            return {
                ...config,
                showReference: false,
                chartMissing: false,

                init() {
                    if (this.activeTab === this.tabId) this.$nextTick(() => this.drawTrend());
                    this.$watch('activeTab', value => {
                        if (value === this.tabId) this.$nextTick(() => this.drawTrend());
                    });
                },

                // The form: each reading flagged as the server will flag it
                num(i) {
                    const v = this.values[i];
                    return v === '' || v === null || v === undefined || isNaN(Number(v)) ? null : Number(v);
                },
                numOf(abbr) {
                    return this.num(this.items.findIndex(item => item.abbr === abbr));
                },
                flag(i) {
                    const v = this.num(i), item = this.items[i];
                    if (v === null) return null;
                    if (item.below !== null && v < item.below) return 'escalate_low';
                    if (item.above !== null && v > item.above) return 'escalate_high';
                    if (v < item.normal[0]) return 'low';
                    if (v > item.normal[1]) return 'high';
                    return 'normal';
                },
                chipClass(i) {
                    const f = this.flag(i);
                    return f ? this.chips[this.flags[f].tone] : '';
                },
                inputClass(i) {
                    return ['', 'border-amber-400 bg-amber-50', 'border-red-400 bg-red-50'][rank(this.flag(i))];
                },
                get entered() {
                    return this.items.filter((item, i) => this.num(i) !== null).length;
                },
                get problem() {
                    for (const abbrs of Object.values(this.groups)) {
                        const count = abbrs.filter(abbr => this.numOf(abbr) !== null).length;
                        if (count > 0 && count < abbrs.length) {
                            return 'Enter ' + abbrs.slice(0, -1).join(', ') + ' and ' + abbrs[abbrs.length - 1] + ' together.';
                        }
                    }
                    for (const [left, op, right, message] of this.checks) {
                        const l = this.numOf(left), r = this.numOf(right);
                        if (l !== null && r !== null && !(op === '<' ? l < r : l > r)) return message;
                    }
                    return null;
                },
                get band() {
                    let grade = null;
                    this.items.forEach((item, i) => {
                        const f = this.flag(i);
                        if (f) grade = Math.max(grade === null ? 0 : grade, this.flags[f].grade);
                    });
                    if (grade === null) return null;
                    return this.bands.find(b => grade >= b.min && grade <= b.max) || null;
                },

                // The trend
                timeWindow() {
                    const times = this.points.map(p => p.t);
                    const hours = RANGES[this.range];
                    let min, max;
                    if (hours) {
                        max = Math.max(this.now, ...times);
                        min = max - hours * HOUR;
                    } else {
                        const first = Math.min(...times), last = Math.max(...times);
                        const pad = Math.max((last - first) * 0.05, 15 * 60000);
                        min = first - pad;
                        max = last + pad;
                    }
                    // Snapped out to whole steps, or Chart.js starts the ticks at an odd minute
                    const span = max - min, step = stepFor(span);
                    return { min: Math.floor(min / step) * step, max: Math.ceil(max / step) * step, step, span };
                },
                get visibleCount() {
                    const w = this.timeWindow();
                    return this.points.filter(p => p.t >= w.min && p.t <= w.max).length;
                },
                setRange(range) {
                    this.range = range;
                    this.drawTrend();
                },
                drawTrend() {
                    if (this.points.length < 2) return;
                    if (typeof Chart === 'undefined') {
                        this.chartMissing = true;
                        return;
                    }
                    const w = this.timeWindow();
                    labelSpan = w.span;
                    ['pressure', 'flow'].forEach(key => {
                        const canvas = this.$refs['chart_' + key];
                        if (!canvas) return;
                        if (charts[key]) {
                            charts[key].options.scales.x.min = w.min;
                            charts[key].options.scales.x.max = w.max;
                            charts[key].options.scales.x.ticks.stepSize = w.step;
                            charts[key].update('none');
                            return;
                        }
                        charts[key] = new Chart(canvas, this.chartConfig(key, w));
                    });
                },
                chartConfig(key, w) {
                    const datasets = this.series.filter(s => s.chart === key).map(s => ({
                        label: s.abbr,
                        unit: s.unit,
                        decimals: s.decimals,
                        yAxisID: s.axis,
                        data: this.points.map(p => ({ x: p.t, y: p.r[s.abbr] ? p.r[s.abbr][0] : null, f: p.r[s.abbr] ? p.r[s.abbr][1] : null })),
                        borderColor: s.color,
                        backgroundColor: s.fill || s.color,
                        fill: s.fill ? '-1' : false,
                        borderWidth: s.width,
                        tension: 0.25,
                        spanGaps: true,
                        pointStyle: 'circle',
                        pointRadius: c => [2.5, 4, 5.5][rank(c.raw && c.raw.f)],
                        pointHoverRadius: 6,
                        pointBackgroundColor: c => [s.color, '#f59e0b', '#dc2626'][rank(c.raw && c.raw.f)],
                        pointBorderColor: c => rank(c.raw && c.raw.f) === 2 ? '#7f1d1d' : '#ffffff',
                        pointBorderWidth: c => rank(c.raw && c.raw.f) === 2 ? 2 : 1,
                    }));

                    const scales = {
                        x: {
                            type: 'linear',
                            min: w.min,
                            max: w.max,
                            grid: { color: '#f3f4f6' },
                            ticks: {
                                stepSize: w.step,
                                maxTicksLimit: 12,
                                maxRotation: 0,
                                color: '#6b7280',
                                font: { size: 11 },
                                callback: value => tickLabel(value),
                            },
                        },
                    };
                    this.axes.filter(a => a.chart === key).forEach(a => {
                        scales[a.id] = {
                            position: a.id === 'y1' ? 'right' : 'left',
                            suggestedMin: a.min,
                            suggestedMax: a.max,
                            title: { display: true, text: a.title, color: '#6b7280', font: { size: 11 } },
                            ticks: { color: '#6b7280', font: { size: 11 } },
                            grid: a.id === 'y1' ? { drawOnChartArea: false } : { color: '#f3f4f6' },
                        };
                    });

                    return {
                        type: 'line',
                        data: { datasets },
                        plugins: [references],
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            interaction: { mode: 'index', intersect: false },
                            scales,
                            plugins: {
                                readingReferences: {
                                    lines: this.lines.filter(l => l.chart === key),
                                    shades: this.shades.filter(s => s.chart === key),
                                },
                                legend: {
                                    position: 'bottom',
                                    labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, font: { size: 11 } },
                                },
                                tooltip: {
                                    filter: item => item.raw && item.raw.y !== null,
                                    callbacks: {
                                        title: items => items.length ? day(items[0].raw.x) + ' ' + clock(items[0].raw.x) : '',
                                        label: item => ' ' + item.dataset.label + ' ' + fmt(item.raw.y, item.dataset.decimals) + ' ' + item.dataset.unit
                                            + (rank(item.raw.f) ? '  (' + this.flags[item.raw.f].label + ')' : ''),
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

<div x-show="activeTab === 'indicator-{{ $indicator['id'] }}'" x-cloak>
<div x-data="clinicalReadingsPanel(@js($panel))">
    {{-- Title, and how the latest readings read overall --}}
    <div class="flex flex-col gap-2 mb-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <h3 class="text-lg font-semibold text-gray-800">{{ $indicator['name'] }}</h3>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-violet-100 text-violet-800">
                {{ $indicator['code'] }}
            </span>
        </div>
        @if ($latest)
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span>
                    Last recorded {{ $latest->recorded_at->format('d M Y H:i') }}
                    @if ($latest->recordedBy)
                        &middot; {{ $latest->recordedBy->name }}
                    @endif
                </span>
                @if ($latest->band_label)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg border text-xs font-semibold {{ $chipClasses[$latest->band_tone] ?? $chipClasses['low'] }}">
                        {{ $latest->band_label }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    @if ($indicator['monitoring'])
        @php
            $monitoring = $indicator['monitoring'];
            $monitoringStyles = [
                'overdue' => ['box' => 'border-red-200 bg-red-50 text-red-800', 'badge' => 'bg-red-600 text-white', 'word' => 'Overdue'],
                'due' => ['box' => 'border-amber-200 bg-amber-50 text-amber-800', 'badge' => 'bg-amber-400 text-white', 'word' => 'Due'],
            ];
            $monitoringStyle = $monitoringStyles[$monitoring['state']] ?? null;
        @endphp
        <div class="mb-3 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm {{ $monitoringStyle['box'] ?? 'border-gray-200 bg-gray-50 text-gray-600' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            @if ($monitoringStyle)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $monitoringStyle['badge'] }}">
                    {{ $monitoringStyle['word'] }}
                </span>
            @endif
            <span>
                <span class="font-semibold">Record every {{ $monitoring['interval'] }}</span>
                &middot; {{ $monitoring['state'] === 'ok' ? $monitoring['label'] : ucfirst($monitoring['history']) }}
            </span>
        </div>
    @endif

    {{-- Latest numerics, one tile per parameter as the monitor shows them --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
        @foreach ($tiles as $tile)
            @php
                $tileEntries = collect($tile['abbrs'])->map(fn ($abbr) => $lastFor[$abbr] ?? null)->filter();
                $tileAt = $tileEntries->first()['at'] ?? null;
                $worstGrade = $tileEntries->max(fn ($entry) => $flags[$entry['flag']]['grade']) ?? 0;
                $worstEntries = $tileEntries->filter(fn ($entry) => $flags[$entry['flag']]['grade'] === $worstGrade);
                $hasPrevious = collect($tile['abbrs'])->every(fn ($abbr) => isset($prevFor[$abbr]));
            @endphp
            <div class="rounded-xl border border-gray-200 border-l-4 bg-white px-3 py-2.5 {{ count($tile['abbrs']) > 1 ? 'col-span-2' : '' }}"
                style="border-left-color: {{ $colours[$tile['abbrs'][0]] }}">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-500" title="{{ $tile['name'] }}">
                        {{ $tile['label'] }} <span class="font-medium normal-case tracking-normal text-gray-400">{{ $tile['unit'] }}</span>
                    </span>
                    @if ($tileAt)
                        <span class="text-[11px] text-gray-400">{{ $when($tileAt) }}</span>
                    @endif
                </div>
                <div class="mt-0.5 text-3xl font-bold leading-tight tabular-nums">
                    {!! $tileEntries->isEmpty() ? '<span class="text-gray-300">&ndash;</span>' : $tileValueHtml($tile['tokens']) !!}
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-gray-500">
                    @if ($worstGrade > 0)
                        @php $worstFlag = $worstEntries->first()['flag']; @endphp
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded border font-semibold {{ $chipClasses[$flags[$worstFlag]['tone']] }}">
                            {{ count($tile['abbrs']) > 1 ? $worstEntries->pluck('abbr')->implode(', ') . ' ' : '' }}{{ strtolower($flags[$worstFlag]['label']) }}
                        </span>
                    @endif
                    <span>Normal {{ $fillTokens($tile['tokens'], fn ($abbr) => $range($itemsByAbbr[$abbr])) }}</span>
                    @if ($hasPrevious)
                        <span class="text-gray-400">
                            &middot; prev {{ $fillTokens($tile['tokens'], fn ($abbr) => $readings::display($prevFor[$abbr], $definition)) }}
                            at {{ $when($prevFor[$tile['abbrs'][0]]['at']) }}
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- How they have moved this admission --}}
    <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Trend this admission</h4>
            @if ($trend->count() >= 2)
                <div class="inline-flex rounded-md border border-gray-200 overflow-hidden text-xs" role="group" aria-label="Time range">
                    @foreach (['6h' => '6h', '12h' => '12h', '24h' => '24h', '72h' => '72h', 'all' => 'All'] as $key => $label)
                        <button type="button" @click="setRange('{{ $key }}')" :aria-pressed="range === '{{ $key }}'"
                            :class="range === '{{ $key }}' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                            class="px-2.5 py-1 font-semibold {{ $loop->last ? '' : 'border-r border-gray-200' }}">{{ $label }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($trend->count() >= 2)
            <p x-show="chartMissing" x-cloak class="mt-3 text-sm text-gray-500">
                The chart could not be drawn because the chart library did not load. The readings are listed below.
            </p>
            @foreach (['pressure' => 'Arterial and central venous pressure', 'flow' => 'Cardiac output and index'] as $chart => $title)
                @if ($charted[$chart])
                    <div class="mt-3">
                        <div class="text-xs font-semibold text-gray-600">{{ $title }}</div>
                        <div class="relative {{ $chart === 'pressure' ? 'h-64' : 'h-52' }}">
                            <canvas x-ref="chart_{{ $chart }}"></canvas>
                            <div x-show="visibleCount === 0" x-cloak
                                class="absolute inset-0 flex items-center justify-center rounded-lg bg-white/80 text-sm text-gray-500">
                                No readings in this period
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
            <p class="mt-2 text-[11px] text-gray-500">
                Dashed lines are escalation levels. Points turn amber outside the normal range and red at an escalation level.
            </p>
        @else
            <div class="mt-3 rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center">
                <p class="text-sm font-medium text-gray-500">The trend appears once two readings are recorded</p>
                <p class="mt-1 text-xs text-gray-400">{{ $trend->isEmpty() ? 'No readings yet this admission.' : 'One reading so far this admission.' }}</p>
            </div>
        @endif
    </div>

    {{-- Record new readings --}}
    <form method="POST" action="{{ route('ward.clinical-indicator-score.store') }}"
        class="mt-4 rounded-xl border border-gray-200 bg-white p-4">
        @csrf
        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
        <input type="hidden" name="clinical_indicator_id" value="{{ $indicator['id'] }}">
        <input type="hidden" name="active_tab" value="indicator-{{ $indicator['id'] }}">

        <div class="flex flex-col gap-1 mb-3 sm:flex-row sm:items-baseline sm:justify-between">
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Record readings</h4>
            <p class="text-[11px] text-gray-500">
                Enter what the patient is monitored for, as the monitor shows it.
                @foreach ($groupMembers as $group => $abbrs)
                    {{ implode(', ', array_slice($abbrs, 0, -1)) }} and {{ end($abbrs) }} go together.
                @endforeach
            </p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-6">
            @foreach ($items as $index => $item)
                @php
                    $decimals = $item['decimals'] ?? 0;
                @endphp
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <label for="ci{{ $indicator['id'] }}_reading{{ $index }}" class="text-sm font-bold text-gray-800"
                            title="{{ $item['name'] }}">
                            {{ $item['abbr'] }} <span class="text-xs font-normal text-gray-500">{{ $item['unit'] }}</span>
                        </label>
                        <span x-show="flag({{ $index }})" x-cloak
                            class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded border text-[10px] font-semibold"
                            :class="chipClass({{ $index }})"
                            x-text="flag({{ $index }}) ? flags[flag({{ $index }})].label : ''"></span>
                    </div>
                    <input type="number" id="ci{{ $indicator['id'] }}_reading{{ $index }}" name="readings[{{ $index }}]"
                        x-model="values[{{ $index }}]" :class="inputClass({{ $index }})"
                        step="{{ $decimals > 0 ? 1 / 10 ** $decimals : 1 }}"
                        min="{{ $item['limits'][0] }}" max="{{ $item['limits'][1] }}" inputmode="decimal"
                        placeholder="{{ $range($item) }}" title="{{ $item['name'] }}: {{ $readings::rangeText($item) }}"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    <p class="mt-1 text-[11px] leading-tight text-gray-500">{{ $readings::rangeText($item, true) }}</p>
                </div>
            @endforeach
        </div>

        <p x-show="problem" x-cloak class="mt-3 text-sm font-medium text-red-700" x-text="problem"></p>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex-1">
                <label for="ci{{ $indicator['id'] }}_notes" class="block text-xs font-semibold text-gray-700 mb-1">Notes
                    (optional)</label>
                <input type="text" id="ci{{ $indicator['id'] }}_notes" name="notes" maxlength="1000"
                    class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                    placeholder="e.g. on noradrenaline 0.1 mcg/kg/min, PEEP 8, trace damped">
            </div>

            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-xs text-gray-500">Status</div>
                    <span x-show="band" x-cloak
                        class="inline-flex items-center px-2.5 py-1 rounded-lg border text-xs font-semibold"
                        :class="band ? chips[band.tone] : ''" x-text="band ? band.label : ''"></span>
                    <span x-show="!band" class="text-2xl font-bold text-gray-800">-</span>
                </div>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-300 disabled:cursor-not-allowed"
                    :disabled="entered === 0 || problem !== null">
                    Save readings
                </button>
            </div>
        </div>
    </form>

    {{-- Every reading this admission, newest first --}}
    <div class="mt-4">
        <div class="flex items-baseline justify-between mb-2">
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Readings this admission</h4>
            @if ($trend->count() > 50)
                <span class="text-[11px] text-gray-500">Latest 50 of {{ $trend->count() }}</span>
            @endif
        </div>
        @if ($trend->isNotEmpty())
            <div class="max-h-80 overflow-y-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 align-bottom">When</th>
                            @foreach ($items as $item)
                                <th class="px-2 py-2 text-left text-xs font-semibold text-gray-600 whitespace-nowrap align-bottom">
                                    {{ $item['abbr'] }}
                                    <span class="block font-normal text-gray-400">{{ $item['unit'] }}</span>
                                </th>
                            @endforeach
                            <th class="px-2 py-2 text-left text-xs font-semibold text-gray-600 align-bottom">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 align-bottom">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach ($trend->reverse()->take(50) as $record)
                            @php
                                $recordedReadings = collect($record->item_scores ?? [])->keyBy('abbr');
                            @endphp
                            <tr class="align-top {{ $record->band_tone === 'high' ? 'bg-red-50' : '' }}">
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    {{ $record->recorded_at->format('d M H:i') }}
                                    <span class="block text-[11px] text-gray-400">{{ $record->recordedBy->name ?? '' }}</span>
                                </td>
                                @foreach ($items as $item)
                                    @php
                                        $entry = $recordedReadings->get($item['abbr']);
                                        $entryFlag = $flags[$entry['flag'] ?? 'normal'] ?? $flags['normal'];
                                    @endphp
                                    <td class="px-2 py-2 whitespace-nowrap tabular-nums {{ $entry ? $cellClasses[$entryFlag['tone']] : 'text-gray-300' }}"
                                        @if ($entry) title="{{ $entry['label'] ?? '' }}" @endif>
                                        {{ $entry ? $readings::display($entry, $definition) . $entryFlag['arrow'] : '-' }}
                                    </td>
                                @endforeach
                                <td class="px-2 py-2 whitespace-nowrap">
                                    @if ($record->band_label)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $chipClasses[$record->band_tone] ?? $chipClasses['low'] }}">
                                            {{ $record->band_label }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 min-w-[10rem] text-xs text-gray-600">{{ $record->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-1.5 text-[11px] text-gray-500">↓ ↑ outside the normal range &middot; ↓↓ ↑↑ at an escalation level</p>
        @else
            <p class="rounded-lg border border-dashed border-gray-300 px-4 py-4 text-center text-sm text-gray-500">No readings yet this admission.</p>
        @endif
    </div>

    <div class="mt-4">
        <button type="button" @click="showReference = !showReference"
            class="inline-flex items-center text-xs font-medium text-blue-600 hover:text-blue-800">
            <svg class="w-3.5 h-3.5 mr-1 transition-transform" :class="showReference ? 'rotate-180' : ''" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
            <span x-text="showReference ? 'Hide parameter details' : 'Show parameter details'">Show parameter details</span>
        </button>
        <div x-show="showReference" x-cloak class="mt-2">
            <x-clinical-indicator-detail :definition="$definition" />
        </div>
    </div>
</div>
</div>
