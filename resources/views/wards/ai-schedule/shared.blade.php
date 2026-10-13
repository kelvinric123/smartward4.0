{{--
    The AI Nurse Schedule opened from a view-only link (AiNurseScheduleShareController@show).
    Nobody is signed in here, so it reads only the link and its public
    ScheduleSnapshot, through the same read-only partials as the printouts, and
    links nowhere but to itself. Made for a phone first; printing gives every
    section. While it is on screen it refreshes its content every 5 minutes.
--}}
@php
    $wardName = $snapshot['ward']['name'];
    $hospital = $snapshot['hospital'];
    $week = $snapshot['weeks'][0];
    $assign = $snapshot['assign'];
    $titles = \App\Models\WardRosterShareLink::SECTIONS;
    // What the read-only partials may show on a public copy (they leave reasons out of one anyway)
    $options = ['mono' => false, 'staffing' => true, 'legend' => true, 'show_reasons' => false, 'write_in' => false, 'references' => false, 'compact' => false];
    $names = ['hidden' => 'by bed only', 'initials' => 'by initials only'][$snapshot['names']] ?? 'by bed only';
    $listsPatients = array_intersect($sections, ['assign', 'acuity']) !== [];
    $listsLeave = array_intersect($sections, ['roster', 'nurse']) !== [];
    $switchClass = fn (bool $current) => $current
        ? 'rounded-md px-3 py-1.5 font-semibold bg-indigo-600 text-white'
        : 'rounded-md px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-100';
    $sharedConfig = ['sections' => $sections, 'refreshHeader' => \App\Http\Controllers\AiNurseScheduleShareController::REFRESH_HEADER];
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $wardName }} &middot; AI Nurse Schedule</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('wards.ai-schedule.readonly.styles')
    <style>
        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Wide tables scroll sideways on a phone instead of squashing */
        .shared-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        @page {
            margin: 10mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
            }

            /* Every section on paper, each from a new page, whichever tab is open */
            .shared-section {
                display: block !important;
                border: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
            }

            .shared-section + .shared-section {
                break-before: page;
            }

            .shared-scroll {
                overflow: visible;
            }
        }
    </style>
    <script>
        window.sharedSchedule = function (config) {
            const REFRESH_EVERY = 5 * 60 * 1000;

            return {
                tab: config.sections[0],
                refreshedAt: Date.now(),

                init() {
                    this.fromHash();
                    window.addEventListener('hashchange', () => this.fromHash());
                    // Only while the page is on screen: checked every half minute, and as soon as it is back in view
                    setInterval(() => this.refreshIfDue(), 30000);
                    document.addEventListener('visibilitychange', () => this.refreshIfDue());
                    // A printout has the method and references open, then they close again as they were
                    window.addEventListener('beforeprint', () => document.querySelectorAll('details').forEach((el) => {
                        el.dataset.wasOpen = el.open ? '1' : '';
                        el.open = true;
                    }));
                    window.addEventListener('afterprint', () => document.querySelectorAll('details').forEach((el) => {
                        el.open = el.dataset.wasOpen === '1';
                    }));
                },
                // The tab is in the address (#roster), so a refresh, a bookmark or the week switch keeps it
                fromHash() {
                    const key = window.location.hash.replace(/^#(section-)?/, '');
                    if (config.sections.includes(key)) this.tab = key;
                },
                go(key) {
                    this.tab = key;
                    history.replaceState(null, '', '#' + key);
                },
                withTab(url) {
                    return url + '#' + this.tab;
                },
                refreshIfDue() {
                    if (document.visibilityState === 'visible' && Date.now() - this.refreshedAt >= REFRESH_EVERY) {
                        this.refresh();
                    }
                },
                // Swaps in the new content only, so the tab, the scroll and anything opened stay as they are
                async refresh() {
                    this.refreshedAt = Date.now();
                    try {
                        const response = await fetch(window.location.href, {
                            headers: { [config.refreshHeader]: '1' },
                            cache: 'no-store',
                            credentials: 'same-origin',
                        });
                        // Revoked or expired since it was opened: show the page that says so
                        if (response.status === 404) {
                            window.location.reload();
                            return;
                        }
                        if (!response.ok) return;
                        const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
                        document.querySelectorAll('[data-refresh]').forEach((region) => {
                            const update = fresh.querySelector('[data-refresh="' + region.dataset.refresh + '"]');
                            if (update) region.innerHTML = update.innerHTML;
                        });
                    } catch (e) {
                        // Off the network for a moment: the next refresh tries again
                    }
                },
            };
        };
    </script>
</head>

<body class="min-h-screen bg-gray-100 text-gray-900" x-data="sharedSchedule(@js($sharedConfig))">
    <header class="border-b border-gray-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-6xl flex-wrap items-start justify-between gap-3 px-4 pt-3">
            <div class="flex min-w-0 items-center gap-3">
                @if ($hospital['logo_url'])
                    <img src="{{ $hospital['logo_url'] }}" alt="" class="h-10 w-auto shrink-0">
                @endif
                <div class="min-w-0">
                    <div class="truncate text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $hospital['name'] }}</div>
                    <h1 class="text-lg font-bold leading-tight text-gray-900">{{ $wardName }}: AI Nurse Schedule</h1>
                    @if ($link->label)
                        <div class="text-sm text-gray-600">{{ $link->label }}</div>
                    @endif
                </div>
            </div>
            <button type="button" onclick="window.print()"
                class="no-print inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print
            </button>
        </div>
        <p class="mx-auto max-w-6xl px-4 pb-3 pt-1 text-xs text-gray-600">
            <span class="font-semibold text-gray-800">View only</span>
            &middot; shared by {{ $link->created_by_name ?: 'the ward' }}
            &middot; link expires {{ $link->expires_at->format('D j M Y, H:i') }}
        </p>
    </header>

    <div class="mx-auto max-w-6xl space-y-3 px-4 py-4">
        {{-- The week, and for a link that follows the current week, this week or the next --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <div data-refresh="week">
                <div class="text-sm font-bold text-gray-900">{{ $week['start']->format('D j M') }} &ndash; {{ $week['end']->format('D j M Y') }}</div>
                <div class="text-[11px] text-gray-500">
                    Week {{ $week['number'] }}{{ $link->isRolling() ? '' : ' · this link shows this week only' }}
                </div>
            </div>
            @if ($link->isRolling())
                <nav class="no-print inline-flex rounded-lg border border-gray-300 bg-white p-0.5 text-sm" aria-label="Week">
                    <a href="{{ $pageUrl }}" :href="withTab(@js($pageUrl))" class="{{ $switchClass(!$next) }}" @if (!$next) aria-current="page" @endif>This week</a>
                    <a href="{{ $pageUrl }}?week=next" :href="withTab(@js($pageUrl . '?week=next'))" class="{{ $switchClass($next) }}" @if ($next) aria-current="page" @endif>Next week</a>
                </nav>
            @endif
        </div>

        @if (count($sections) > 1)
            <nav class="no-print overflow-x-auto border-b border-gray-200" aria-label="Sections">
                <div class="flex gap-1">
                    @foreach ($sections as $key)
                        <a href="#section-{{ $key }}" @click.prevent="go('{{ $key }}')"
                            :class="tab === '{{ $key }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium">{{ $titles[$key]['label'] }}</a>
                    @endforeach
                </div>
            </nav>
        @endif

        @foreach ($sections as $key)
            <section id="section-{{ $key }}" class="shared-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5"
                x-show="tab === '{{ $key }}'" @if (!$loop->first) x-cloak @endif>
                <div class="mb-3">
                    <h2 class="text-base font-bold text-gray-900">{{ $titles[$key]['label'] }}</h2>
                    <p class="text-xs text-gray-500">
                        {{ $titles[$key]['description'] }}.
                        @if ($key === 'acuity')
                            <a href="#acuity-references" onclick="document.getElementById('acuity-references').open = true"
                                class="no-print font-semibold text-indigo-700 hover:text-indigo-900">How SmartWard suggests levels and staffing</a>
                        @endif
                    </p>
                </div>

                <div class="shared-scroll" data-refresh="{{ $key }}">
                    @switch($key)
                        @case('nurse')
                            @include('wards.ai-schedule.readonly.nurse-list', ['snapshot' => $snapshot, 'options' => $options])
                            @break

                        @case('assign')
                            {{-- Another day or shift of the week; the shift on now unless one is picked --}}
                            <form method="GET" action="{{ $pageUrl }}#assign" class="no-print mb-3 flex flex-wrap items-end gap-3">
                                @if ($next)
                                    <input type="hidden" name="week" value="next">
                                @endif
                                <label class="text-xs font-semibold text-gray-600">
                                    Day
                                    <select name="day" onchange="this.form.submit()"
                                        class="mt-0.5 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach ($week['dates'] as $date)
                                            <option value="{{ $date->toDateString() }}" @selected($date->toDateString() === $assign['day'])>
                                                {{ $date->format('D j M') }}{{ $date->toDateString() === $snapshot['today'] ? ' (today)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-gray-600">
                                    Shift
                                    <select name="shift" onchange="this.form.submit()"
                                        class="mt-0.5 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach ($snapshot['shifts'] as $code => $info)
                                            <option value="{{ $code }}" @selected($code === $assign['shift'])>{{ $code }} &middot; {{ $info['time'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <noscript>
                                    <button type="submit" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700">Show</button>
                                </noscript>
                            </form>
                            @include('wards.ai-schedule.readonly.beds', ['snapshot' => $snapshot, 'options' => $options])
                            @break

                        @case('acuity')
                            @include('wards.ai-schedule.readonly.acuity', ['snapshot' => $snapshot, 'options' => $options])
                            @break

                        @case('workload')
                            @include('wards.ai-schedule.readonly.workload', ['snapshot' => $snapshot, 'options' => $options])
                            @break

                        @default
                            @include('wards.ai-schedule.readonly.roster', ['snapshot' => $snapshot, 'options' => $options])
                    @endswitch
                </div>

                @if ($key === 'acuity')
                    {{-- The method is SmartWard's own (its indicators, levels and hours); ANA is the source of the approach and the references --}}
                    <details id="acuity-references" class="mt-4 rounded-xl border border-gray-200 bg-gray-50/70">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-800">
                            How SmartWard suggests levels and staffing, and the ANA (2017) references
                        </summary>
                        <div class="space-y-6 border-t border-gray-200 px-4 py-4">
                            @include('wards.ai-schedule.partials.acuity-method', ['acuitySettings' => $snapshot['acuity']['settings'], 'shifts' => $snapshot['shifts']])
                            @include('wards.ai-schedule.partials.acuity-references')
                        </div>
                    </details>
                @endif
            </section>
        @endforeach
    </div>

    <footer class="mx-auto max-w-6xl space-y-1 px-4 pb-6 text-xs text-gray-500">
        <p data-refresh="updated">
            SmartWard AI Nurse Schedule &middot; {{ $wardName }} &middot; {{ $hospital['name'] }}
            &middot; as of {{ $snapshot['generated_at']->format('D j M Y, H:i') }}<span class="no-print">, refreshed every 5 minutes while open</span>.
        </p>
        <p>
            A view-only copy for ward staff.
            @if ($listsPatients)
                Patients are shown {{ $names }}.
            @endif
            @if ($listsLeave)
                Leave shows without its type.
            @endif
            Please do not pass the link outside the ward team.
        </p>
        @if ($snapshot['acuity'])
            <p>
                Patient acuity and acuity-based staffing after the American Nurses Association (2017), Defining staffing:
                workforce management, patient classification and acuity systems. A planning aid: the nurse's judgement decides.
            </p>
        @endif
    </footer>
</body>

</html>
