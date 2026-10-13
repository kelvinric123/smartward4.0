{{--
    Print button and dialog for the AI Nurse Schedule, in the ward and week bar.
    It sits inside the page's Alpine scope, so it opens on the format of the tab
    being looked at. Every choice goes into the printout's URL (checked again by
    AiNurseSchedulePrintController); the last choices are remembered in this
    browser, except full patient names: a shared ward PC must not hand the next
    person a sheet of names and MRNs they did not ask for.

    The reports sit in a group of their own. The productivity and credentialing
    reports, and leave notes, are about the staff, so they are offered only to
    those the controller lets print them (canPrintStaffReports: not nurses,
    not the shared ward screen login).
--}}
@php
    $printController = \App\Http\Controllers\AiNurseSchedulePrintController::class;
    $canPrintStaffReports = $printController::canPrintStaffReports();
    $printFormats = collect($printController::FORMATS)->reject(fn (array $format) => ($format['editors'] ?? false) && !$canPrintStaffReports);
    $printSections = collect($printController::SECTIONS)->reject(fn (array $section, string $key) => $key === 'leave_notes' && !$canPrintStaffReports);
    $printMonth = $printController::month(null, $weekStart);
    $monthLimit = $printController::MONTHS_AROUND;
    $monthMin = now()->subMonthsNoOverflow($monthLimit)->format('Y-m');
    $monthMax = now()->addMonthsNoOverflow($monthLimit)->format('Y-m');
    $printConfig = [
        'url' => route('ward.ai-schedule.print'),
        'ward' => $ward->id,
        'week' => $weekStart->toDateString(),
        'day' => $day,
        'shift' => $shift,
        'month' => $printMonth->format('Y-m'),
        'monthMin' => $monthMin,
        'monthMax' => $monthMax,
        'formats' => $printFormats
            ->map(fn (array $format) => \Illuminate\Support\Arr::only($format, ['orientation', 'weeks', 'shift', 'patients', 'sections'])
                + ['month' => $format['month'] ?? false, 'period' => $format['period'] ?? false, 'window' => $format['window'] ?? false]),
        'sections' => $printSections->map(fn (array $section) => $section['default']),
        'papers' => $printController::PAPERS,
        'orientations' => $printController::ORIENTATIONS,
        'sizes' => $printController::SIZES,
        // Only these are remembered; full names are chosen afresh each time
        'rememberedNames' => ['initials', 'hidden'],
        'weeks' => \App\Services\NurseScheduling\ScheduleSnapshot::WEEKS,
        'periods' => \App\Services\NurseScheduling\LeaveReport::PERIODS,
        'windows' => \App\Services\NurseScheduling\CredentialReport::WINDOWS,
        'tabFormats' => $printController::TAB_FORMATS,
    ];
    $printControl = 'rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500';
@endphp

<script>
    window.aiSchedulePrint = function (config) {
        const STORAGE_KEY = 'smartward.aiSchedule.print';
        let saved = {};
        try {
            saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {};
        } catch (e) {
            saved = {};
        }
        const pick = (value, allowed, fallback) => allowed.includes(value) ? value : fallback;
        // Leave notes can hold health details, so like full names they are never remembered
        const PRIVATE_SECTIONS = ['leave_notes'];
        const sections = {};
        Object.keys(config.sections).forEach((key) => {
            const remembered = !PRIVATE_SECTIONS.includes(key) && typeof (saved.sections || {})[key] === 'boolean';
            sections[key] = remembered ? saved.sections[key] : config.sections[key];
        });

        return {
            open: false,
            format: 'roster',
            paper: pick(saved.paper, config.papers, 'A4'),
            orientation: pick(saved.orientation, config.orientations, 'auto'),
            weeks: pick(saved.weeks, config.weeks, 1),
            // Full names never come back from storage, even if an older version saved them
            names: pick(saved.names, config.rememberedNames, 'initials'),
            size: pick(saved.size, config.sizes, 'normal'),
            mono: saved.mono === true,
            day: config.day,
            shift: config.shift,
            month: config.month,
            period: pick(saved.period, config.periods, 'month'),
            withinDays: pick(saved.window, config.windows, 90),
            sections,

            // Opens on the format of the tab in view, for the day, shift and month shown
            show(tab) {
                this.format = config.tabFormats[tab] || 'roster';
                this.day = config.day;
                this.shift = config.shift;
                this.month = config.month;
                // Full names and leave notes are asked for each time, never carried over
                if (!config.rememberedNames.includes(this.names)) this.names = 'initials';
                PRIVATE_SECTIONS.filter((key) => key in config.sections).forEach((key) => { this.sections[key] = config.sections[key]; });
                this.open = true;
                this.$nextTick(() => this.$refs.printPanel && this.$refs.printPanel.focus());
            },
            get spec() {
                return config.formats[this.format] || config.formats.roster;
            },
            has(section) {
                return this.spec.sections.includes(section);
            },
            // Where the browser shows the month as a plain text box, anything typed must be YYYY-MM in range:
            // the printout would otherwise quietly fall back to the default month
            get monthOk() {
                const month = (this.month || '').trim();
                return !this.spec.month
                    || (/^\d{4}-(0[1-9]|1[0-2])$/.test(month) && month >= config.monthMin && month <= config.monthMax);
            },
            url(autoprint) {
                const params = new URLSearchParams();
                params.set('ward_id', config.ward);
                params.set('week', config.week);
                params.set('format', this.format);
                params.set('paper', this.paper);
                params.set('orientation', this.orientation);
                if (this.spec.weeks) params.set('weeks', this.weeks);
                if (this.spec.shift) {
                    params.set('day', this.day);
                    params.set('shift', this.shift);
                }
                if (this.spec.patients) params.set('names', this.names);
                if (this.spec.month) params.set('month', (this.month || '').trim());
                if (this.spec.period) params.set('period', this.period);
                if (this.spec.window) params.set('window', this.withinDays);
                // "none" keeps every section off when none is ticked, rather than the defaults
                const on = this.spec.sections.filter((key) => this.sections[key]);
                (on.length ? on : ['none']).forEach((key) => params.append('sections[]', key));
                params.set('size', this.size);
                if (this.mono) params.set('mono', '1');
                if (autoprint) params.set('autoprint', '1');

                return config.url + '?' + params.toString();
            },
            go(autoprint) {
                if (!this.monthOk) {
                    this.$refs.printMonth && this.$refs.printMonth.focus();
                    return;
                }
                try {
                    // Full names and leave notes are left out, so the next print here starts without them
                    const kept = {};
                    Object.keys(this.sections).filter((key) => !PRIVATE_SECTIONS.includes(key)).forEach((key) => { kept[key] = this.sections[key]; });
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({
                        paper: this.paper, orientation: this.orientation, weeks: this.weeks,
                        names: config.rememberedNames.includes(this.names) ? this.names : undefined,
                        size: this.size, mono: this.mono, sections: kept,
                        period: this.period, window: this.withinDays,
                    }));
                } catch (e) {
                    // Private browsing or storage switched off: nothing is remembered
                }
                const url = this.url(autoprint);
                this.open = false;
                // A new tab keeps the schedule open; fall back to this tab if pop-ups are blocked
                if (!window.open(url, '_blank')) window.location.href = url;
            },
        };
    };
</script>

<div x-data="aiSchedulePrint(@js($printConfig))" @keydown.escape.window="open = false">
    <button type="button" @click="show(tab)" title="Print the roster, bed assignment, patient acuity, workload or a report"
        class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
        Print
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-3"
        role="dialog" aria-modal="true" aria-labelledby="ai-print-title">
        <div class="absolute inset-0 bg-gray-900/50" @click="open = false"></div>

        <div x-ref="printPanel" tabindex="-1"
            class="relative flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl focus:outline-none">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
                <div>
                    <h3 id="ai-print-title" class="text-base font-semibold text-gray-900">Print the AI Nurse Schedule</h3>
                    <p class="text-xs text-gray-500">{{ $ward->ward_name }} &middot; week of {{ $weekStart->format('j M Y') }}. Opens a print view; save it as a PDF from there.</p>
                </div>
                <button type="button" @click="open = false" class="p-1 text-gray-400 hover:text-gray-600" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="grid flex-1 grid-cols-1 gap-5 overflow-y-auto px-5 py-4 md:grid-cols-2">
                {{-- What to print --}}
                <section>
                    @foreach ($printFormats->groupBy(fn (array $format) => ($format['report'] ?? false) ? 'Reports' : 'Format', true) as $group => $formats)
                        <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500 {{ $loop->first ? '' : 'mt-4' }}">{{ $group }}</h4>
                        <div class="space-y-1.5">
                            @foreach ($formats as $key => $format)
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border px-3 py-2"
                                    :class="format === '{{ $key }}' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200 hover:bg-gray-50'">
                                    <input type="radio" name="ai_print_format" value="{{ $key }}" x-model="format"
                                        class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-gray-800">{{ $format['label'] }}</span>
                                        <span class="block text-xs text-gray-500">{{ $format['description'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                    @unless ($canPrintStaffReports)
                        <p class="mt-2 text-xs text-gray-500">The productivity and credentialing reports are for nurse managers and admins.</p>
                    @endunless
                </section>

                {{-- How it looks --}}
                <div class="space-y-4">
                    <section class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="ai_print_paper" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500">Paper</label>
                            <select id="ai_print_paper" x-model="paper" class="{{ $printControl }} w-full">
                                @foreach ($printController::PAPERS as $paper)
                                    <option value="{{ $paper }}">{{ $paper }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="spec.weeks">
                            <label for="ai_print_weeks" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500">Weeks</label>
                            <select id="ai_print_weeks" x-model.number="weeks" class="{{ $printControl }} w-full">
                                <option value="1">This week</option>
                                <option value="2">2 weeks</option>
                                <option value="4">4 weeks</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Orientation</h4>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-700">
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="ai_print_orientation" value="auto" x-model="orientation" class="text-indigo-600 focus:ring-indigo-500">
                                    <span>Auto (<span x-text="spec.orientation"></span>)</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="ai_print_orientation" value="landscape" x-model="orientation" class="text-indigo-600 focus:ring-indigo-500">
                                    Landscape
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="ai_print_orientation" value="portrait" x-model="orientation" class="text-indigo-600 focus:ring-indigo-500">
                                    Portrait
                                </label>
                            </div>
                        </div>
                    </section>

                    <section x-show="spec.shift" class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="ai_print_day" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500">Day</label>
                            <select id="ai_print_day" x-model="day" class="{{ $printControl }} w-full">
                                @foreach ($board['dates'] as $date)
                                    <option value="{{ $date->toDateString() }}">{{ $date->format('D j M') }}{{ $date->isToday() ? ' (today)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="ai_print_shift" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500">Shift</label>
                            <select id="ai_print_shift" x-model="shift" class="{{ $printControl }} w-full">
                                @foreach (\App\Services\NurseScheduling\WardShifts::CODES as $code)
                                    <option value="{{ $code }}">{{ $code }}{{ isset($shifts[$code]) ? ' · ' . $shifts[$code]['time'] : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </section>

                    {{-- A report's month, the period around it, or how far ahead "expiring" looks --}}
                    <section x-show="spec.month || spec.window" class="grid grid-cols-2 gap-3">
                        <div x-show="spec.month">
                            <label for="ai_print_month" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500">Month</label>
                            <input type="month" id="ai_print_month" x-ref="printMonth" x-model="month" class="{{ $printControl }} w-full"
                                min="{{ $monthMin }}" max="{{ $monthMax }}" placeholder="YYYY-MM" pattern="\d{4}-\d{2}"
                                :aria-invalid="monthOk ? 'false' : 'true'" aria-describedby="ai_print_month_error">
                            <p id="ai_print_month_error" x-show="!monthOk" class="mt-1 text-[11px] text-red-700">
                                Type the month as YYYY-MM, e.g. {{ $printMonth->format('Y-m') }}, from {{ $monthMin }} to {{ $monthMax }}.
                            </p>
                        </div>
                        <div x-show="spec.period">
                            <label for="ai_print_period" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500">Period</label>
                            <select id="ai_print_period" x-model="period" class="{{ $printControl }} w-full">
                                <option value="month">The month</option>
                                <option value="quarter">Its quarter</option>
                                <option value="year">Its year</option>
                            </select>
                        </div>
                        <div x-show="spec.window" class="col-span-2">
                            <label for="ai_print_window" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500">Expiring within</label>
                            <select id="ai_print_window" x-model.number="withinDays" class="{{ $printControl }} w-full">
                                @foreach (\App\Services\NurseScheduling\CredentialReport::WINDOWS as $days)
                                    <option value="{{ $days }}">{{ $days }} days</option>
                                @endforeach
                            </select>
                        </div>
                    </section>

                    <section x-show="spec.patients">
                        <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Patient names</h4>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-700">
                            <label class="flex items-center gap-2"><input type="radio" name="ai_print_names" value="initials" x-model="names" class="text-indigo-600 focus:ring-indigo-500"> Initials</label>
                            <label class="flex items-center gap-2"><input type="radio" name="ai_print_names" value="hidden" x-model="names" class="text-indigo-600 focus:ring-indigo-500"> Bed only</label>
                            <label class="flex items-center gap-2"><input type="radio" name="ai_print_names" value="full" x-model="names" class="text-indigo-600 focus:ring-indigo-500"> Full names and MRN</label>
                        </div>
                        <p x-show="names === 'full'" class="mt-1 text-[11px] text-amber-700">Full names and MRNs: keep the printout on the ward and shred it after use.</p>
                    </section>

                    <section class="grid grid-cols-2 gap-3">
                        <div>
                            <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Text size</h4>
                            <div class="space-y-0.5 text-sm text-gray-700">
                                <label class="flex items-center gap-2"><input type="radio" name="ai_print_size" value="compact" x-model="size" class="text-indigo-600 focus:ring-indigo-500"> Compact</label>
                                <label class="flex items-center gap-2"><input type="radio" name="ai_print_size" value="normal" x-model="size" class="text-indigo-600 focus:ring-indigo-500"> Normal</label>
                                <label class="flex items-center gap-2"><input type="radio" name="ai_print_size" value="large" x-model="size" class="text-indigo-600 focus:ring-indigo-500"> Large</label>
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Colour</h4>
                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                <input type="checkbox" x-model="mono" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>Black and white <span class="block text-xs text-gray-500">For printers without colour</span></span>
                            </label>
                        </div>
                    </section>

                    <section>
                        <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Include</h4>
                        <div class="space-y-0.5">
                            @foreach ($printSections as $key => $section)
                                <label x-show="has('{{ $key }}')" class="flex cursor-pointer items-start gap-2 rounded px-1 py-1 text-sm text-gray-700 hover:bg-gray-50">
                                    <input type="checkbox" x-model="sections.{{ $key }}" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>
                                        {{ $section['label'] }}
                                        <span class="block text-xs text-gray-500">{{ $section['hint'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3">
                <button type="button" @click="open = false"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" @click="go(false)" :disabled="!monthOk"
                    class="px-4 py-2 bg-white border border-indigo-200 text-indigo-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    Preview
                </button>
                <button type="button" @click="go(true)" :disabled="!monthOk"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print
                </button>
            </div>
        </div>
    </div>
</div>
