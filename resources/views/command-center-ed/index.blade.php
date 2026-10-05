<x-app-layout>
    @include('command-center-v2.partials.styles')
    @verbatim
    <style>
        /* Command Center V2 (ED): the V2 board's look (partials/styles), with the ED's zones and times */
        .cced .zone-dot { display: inline-block; width: .75em; height: .75em; border-radius: 50%; flex-shrink: 0; background: var(--ink-3); box-shadow: 0 0 0 2px var(--surface); }
        .cced .zone-dot.is-red { background: #e5484d; }
        .cced .zone-dot.is-yellow { background: #f5b700; }
        .cced .zone-dot.is-green { background: #30a46c; }
        .cced .ccx-item .title { display: inline-flex; align-items: center; gap: .45em; }
        .cced .ccx-docs { display: grid; gap: .25em; margin-top: 1em; padding-top: .6em; border-top: 1px solid var(--line); }
        .cced .ccx-docs li { display: flex; align-items: baseline; justify-content: space-between; gap: .75em; padding: .12em 0; }
        .cced .ccx-docs .who { min-width: 0; color: var(--ink-2); font-size: .929em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cced .ccx-docs .n { font-weight: 650; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .cced .ccx-docs .n small { font-weight: 500; font-size: .8em; color: var(--ink-3); margin-left: .3em; }
        .cced .ccx-hbar .fill.is-over { background: var(--warning); }
        .cced .ccx-cols .bar.is-ghost { background: var(--series-2); opacity: .55; }
        .cced .ccx-target { display: inline-flex; align-items: center; gap: .35em; }
        .ccx.is-tv.cced .ccx-docs { display: none; }
    </style>
    @endverbatim

    <div class="ccx cced" data-theme="dark" x-data="commandCenterEd"
        data-url="{{ route('command-center-ed.data') }}" data-settings-url="{{ route('command-center-ed.settings') }}"
        :data-theme="theme" :class="{ 'is-tv': tv, 'no-kpis': !cfg.kpis.show, 'no-attention': !cfg.attention.show }" :style="boardStyle"
        @keydown.escape.window="tv && !settingsOpen && setTv(false)">
        <script>
            // The theme this screen was left on, before the first paint
            try { if (localStorage.getItem('ccedTheme') === 'light') document.currentScript.parentElement.dataset.theme = 'light'; } catch (e) {}
        </script>

        <div class="ccx-progress" aria-hidden="true">
            <template x-for="c in [cycle]" :key="c">
                <span :class="{ 'is-stopped': connection !== 'live' }" :style="`animation-duration: ${s.refresh_seconds}s`"></span>
            </template>
        </div>

        {{-- ------ Header ------ --}}
        <header class="ccx-top">
            <div class="ccx-brand">
                <span class="ccx-logo"><img src="{{ $logoUrl }}" alt="{{ $hospital?->name ?? 'Hospital' }} logo"></span>
                <div style="min-width: 0">
                    <p class="ccx-eyebrow">{{ $hospital?->name ?? config('app.name') }}</p>
                    <h1>Command Center V2 (ED)</h1>
                    <p class="ccx-sub">Emergency department &middot; every zone, live</p>
                </div>
            </div>

            <div class="ccx-status" role="status" aria-live="polite">
                <span class="ccx-pill" :class="'is-' + connection"><span class="dot"></span><span x-text="connectionLabel">Live</span></span>
                <span class="ccx-updated" x-text="(connection === 'live' ? 'Updated ' : 'Last update ') + s.generated_time + ' · every ' + s.refresh_seconds + ' s'"></span>
            </div>

            <div class="ccx-clock" aria-hidden="true">
                <p class="time"><span x-text="clockTime"></span><span class="sec" x-text="clockSeconds"></span></p>
                <p class="date" x-text="clockDate"></p>
            </div>

            <div class="ccx-actions">
                <a href="{{ route('command-center-v2.index') }}" class="ccx-btn" x-show="!tv" title="Command Center V2: every inpatient ward">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 13v-1m4 1v-3m4 3V8M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" /></svg>
                    Wards view
                </a>
                <button type="button" class="ccx-btn" @click="openSettings()" aria-haspopup="dialog" title="The time-in-ED target, and this screen's size, sections and timing">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Settings
                </button>
                <button type="button" class="ccx-btn is-square" @click="toggleTheme()" :aria-label="theme === 'dark' ? 'Switch to the light theme' : 'Switch to the dark theme'" :title="theme === 'dark' ? 'Light theme' : 'Dark theme'">
                    <svg x-show="theme === 'dark'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    <svg x-show="theme === 'light'" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                </button>
                <button type="button" class="ccx-btn" @click="setTv(!tv)" :aria-pressed="tv.toString()" title="Full screen for the command centre wall, without the menu">
                    <svg x-show="!tv" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                    <svg x-show="tv" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    <span x-text="tv ? 'Exit TV mode' : 'TV mode'">TV mode</span>
                </button>
            </div>
        </header>

        <div class="ccx-banner" x-show="connection === 'signed-out'" x-cloak role="alert">
            <span class="ccx-ico is-critical" x-html="icon('critical')"></span>
            <span>Signed out, so these figures have stopped updating.</span>
            <button type="button" class="ccx-btn" @click="window.location.reload()">Sign in again</button>
        </div>

        <div class="ccx-body">
        {{-- ------ The ED at a glance ------ --}}
        <section class="ccx-section ccx-kpis" aria-label="Key figures" x-show="cfg.kpis.show">
            {{-- In the ED now --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.now }">
                <header>
                    <h2>In the ED now</h2>
                    <span class="ccx-chip" :class="'is-' + s.now.occupancy_status" x-show="s.now.beds">
                        <span class="ccx-ico" x-html="icon(s.now.occupancy_status)"></span>
                        <span x-text="occupancyLabel(s.now.occupancy_status)"></span>
                    </span>
                </header>
                <div class="ccx-hero">
                    <span class="num" x-text="fmt(s.now.patients)"></span>
                    <span class="cap" x-text="`patients in ${fmt(s.now.zones)} zones · ${fmt(s.now.occupied)} of ${fmt(s.now.beds)} beds`"></span>
                </div>
                <div class="ccx-meter" :class="'is-' + s.now.occupancy_status" role="img" :aria-label="`${dec(s.now.occupancy)}% of ED beds taken`">
                    <span class="fill" :style="`width: ${Math.min(100, s.now.occupancy || 0)}%`"></span>
                    <span class="tick" :style="`left: ${s.targets.busy}%`"></span>
                </div>
                <div class="ccx-meter-legend"><span>0%</span><span x-text="s.now.occupancy === null ? 'No beds yet' : `${dec(s.now.occupancy)}% of beds taken`"></span><span>100%</span></div>
                <dl class="ccx-minis">
                    <div><dt>Waiting area</dt><dd x-text="fmt(s.now.waiting)"></dd></div>
                    <div><dt>Free beds</dt><dd x-text="fmt(s.now.free)"></dd></div>
                    <div><dt>Longest in ED</dt><dd x-text="hm(s.now.longest)"></dd></div>
                </dl>
            </article>

            {{-- Time in ED against the target --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.time }">
                <header>
                    <h2>Time in ED</h2>
                    <span class="ccx-chip" :class="'is-' + s.now.time_status">
                        <span class="ccx-ico" x-html="icon(s.now.time_status)"></span>
                        <span x-text="timeLabel(s.now.time_status)"></span>
                    </span>
                </header>
                <div class="ccx-hero">
                    <span class="num" x-text="pct(s.now.within_pct)"></span>
                    <span class="cap" x-text="`within the ${hm(s.targets.minutes)} target`"></span>
                </div>
                <div class="ccx-meter" :class="'is-' + (s.now.over_target ? (s.now.over_double ? 'serious' : 'warning') : 'good')" role="img"
                    :aria-label="`${s.now.within_target} of ${s.now.known} patients within the target`">
                    <span class="fill" :style="`width: ${s.now.within_pct || 0}%`"></span>
                </div>
                <div class="ccx-meter-legend"><span x-text="`${fmt(s.now.within_target)} of ${fmt(s.now.known)} patients`"></span><span x-text="s.now.unknown ? `${s.now.unknown} without an arrival time` : 'Since arrival'"></span></div>
                <dl class="ccx-rows">
                    <div>
                        <dt><span class="ccx-ico is-warning" x-show="s.now.over_target" x-html="icon('warning')"></span><span x-text="`Over ${hm(s.targets.minutes)}`"></span></dt>
                        <dd><span x-text="fmt(s.now.over_target)"></span><small x-text="`${fmt(s.now.over_double)} over ${hm(2 * s.targets.minutes)}`"></small></dd>
                    </div>
                    <div><dt>Median time in ED</dt><dd x-text="hm(s.now.median)"></dd></div>
                    <div><dt>Longest</dt><dd><span x-text="hm(s.now.longest)"></span><small x-text="s.now.longest_zone || ''"></small></dd></div>
                </dl>
            </article>

            {{-- Flow today --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.flow }">
                <header>
                    <h2>ED flow today</h2>
                    <span class="ccx-muted">Since midnight</span>
                </header>
                <div class="ccx-pair">
                    <div>
                        <p class="lbl">Arrivals</p>
                        <p class="num" x-text="fmt(s.flow.arrivals_today)"></p>
                        <p class="ccx-delta" x-text="versus(s.flow.arrivals_today, s.flow.arrivals_same_time_yesterday, 'same time yesterday')"></p>
                    </div>
                    <div>
                        <p class="lbl">Left the ED</p>
                        <p class="num" x-text="fmt(s.flow.departures_today)"></p>
                        <p class="ccx-delta" x-text="versus(s.flow.departures_today, s.flow.departures_same_time_yesterday, 'same time yesterday')"></p>
                    </div>
                </div>
                <dl class="ccx-rows">
                    <div>
                        <dt>Admitted to a ward</dt>
                        <dd><span x-text="fmt(s.flow.admitted)"></span><small x-text="s.flow.admission_rate === null ? '' : pct(s.flow.admission_rate) + ' of those who left'"></small></dd>
                    </div>
                    <div><dt>Discharged home</dt><dd x-text="fmt(s.flow.home)"></dd></div>
                    <div>
                        <dt>Median stay of those who left</dt>
                        <dd><span x-text="hm(s.flow.stay_median)"></span><small x-show="s.flow.stays_known" x-text="`${s.flow.left_within_target} of ${s.flow.stays_known} within ${hm(s.targets.minutes)}`"></small></dd>
                    </div>
                </dl>
            </article>

            {{-- Doctors --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.doctors }">
                <header>
                    <h2>Doctors</h2>
                    <span class="ccx-muted" x-text="s.doctors.count ? `${s.doctors.count} with ED patients` : ''"></span>
                </header>
                <template x-if="s.doctors.count">
                    <div>
                        <div class="ccx-hero">
                            <span class="num is-mid" x-text="dec(s.doctors.per_doctor)"></span>
                            <span class="cap">patients per doctor</span>
                        </div>
                        <ol class="ccx-docs">
                            <template x-for="d in s.doctors.rows.slice(0, 4)" :key="d.name">
                                <li>
                                    <span class="who" x-text="d.name" :title="d.name"></span>
                                    <span class="n"><span x-text="fmt(d.patients)"></span><small x-show="d.over_target" x-text="`${d.over_target} over`"></small></span>
                                </li>
                            </template>
                        </ol>
                        <dl class="ccx-rows">
                            <div><dt>No doctor assigned</dt><dd x-text="fmt(s.doctors.unassigned)"></dd></div>
                        </dl>
                    </div>
                </template>
                <template x-if="!s.doctors.count">
                    <div class="ccx-empty">
                        <p x-text="s.now.patients ? 'No doctor recorded for the patients in the ED.' : 'Nobody in the ED right now.'"></p>
                    </div>
                </template>
            </article>
        </section>

        {{-- ------ TV mode: the page up beside the attention list ------ --}}
        <nav class="ccx-pager" x-show="tv" x-cloak aria-label="Pages">
            <template x-for="page in tvPages" :key="page.key">
                <button type="button" class="ccx-tab" :class="{ 'is-on': tvPage === page.key }" @click="showTv(page.key)" :aria-current="tvPage === page.key">
                    <span x-text="page.label"></span>
                    <span class="count" x-show="tvPage === page.key && subPages > 1" x-text="`${subPage + 1}/${subPages}`"></span>
                </button>
            </template>
            <button type="button" class="ccx-tab ccx-hold" :class="{ 'is-on': paused }" @click="paused = !paused"
                :aria-pressed="paused.toString()" :title="paused ? 'Carry on turning the pages' : 'Keep this page up'">
                <svg x-show="!paused" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><rect x="5" y="4" width="3.5" height="12" rx="1" /><rect x="11.5" y="4" width="3.5" height="12" rx="1" /></svg>
                <svg x-show="paused" x-cloak viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.5 4.2v11.6a.8.8 0 0 0 1.2.7l9-5.8a.8.8 0 0 0 0-1.4l-9-5.8a.8.8 0 0 0-1.2.7Z" /></svg>
                <span x-text="paused ? 'Paused · resume' : 'Pause'">Pause</span>
            </button>
            <span class="bar" aria-hidden="true">
                <template x-for="k in [stepKey]" :key="k">
                    <span :class="{ 'is-stopped': paused || settingsOpen }" :style="`animation-duration: ${stepSeconds}s`"></span>
                </template>
            </span>
        </nav>

        {{-- ------ Every zone, and what needs attention ------ --}}
        <section class="ccx-section ccx-mid">
            <article class="ccx-card ccx-wards ccx-page" :class="{ 'is-current': tvPage === 'zones' }" x-show="cfg.zones.show">
                <header>
                    <h2>Zones</h2>
                    <span class="ccx-muted" x-show="!tv">A zone's name opens its dashboard</span>
                    <span class="ccx-pagecount" x-show="tv && tvPage === 'zones' && subPages > 1" x-text="`Page ${subPage + 1} of ${subPages}`"></span>
                </header>
                <div class="ccx-tablewrap" x-ref="zones">
                    <table class="ccx-table">
                        <thead>
                            <tr>
                                <th scope="col">Zone</th>
                                <th scope="col">Beds taken</th>
                                <th scope="col" title="Beds free (closed ones not counted)">Free</th>
                                <th scope="col" x-text="`Over ${hm(s.targets.minutes)}`"></th>
                                <th scope="col" title="Median time in the ED of the patients in this zone">Median</th>
                                <th scope="col" title="The longest any patient in this zone has been in the ED">Longest</th>
                                <th scope="col" title="Arrived in and left from this zone since midnight">In / out today</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="z in s.zones" :key="z.id">
                                <tr data-row :class="{ 'is-changed': changed['zone.' + z.id] }">
                                    <td>
                                        <div class="ccx-ward">
                                            <span class="ccx-ico" :class="'is-' + z.status" x-html="icon(z.status)" role="img" :aria-label="sevLabel(z.status)" :title="sevLabel(z.status)"></span>
                                            <span class="zone-dot" :class="z.colour ? 'is-' + z.colour : ''" aria-hidden="true"></span>
                                            <div>
                                                <a :href="z.url" class="name" x-text="z.short"></a>
                                                <div class="meta" x-text="z.code"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ccx-occ">
                                            <div class="ccx-meter" :class="z.full ? 'is-' + (z.colour === 'red' ? 'serious' : 'warning') : 'is-good'" aria-hidden="true">
                                                <span class="fill" :style="`width: ${Math.min(100, z.occupancy || 0)}%`"></span>
                                            </div>
                                            <span class="val">
                                                <span x-text="`${z.occupied}/${z.beds}`"></span>
                                                <span class="ccx-sub-num" x-show="z.closed" x-text="`${z.closed} closed`"></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td x-text="fmt(z.free)"></td>
                                    <td>
                                        <span class="ccx-flag">
                                            <span class="ccx-ico" :class="'is-' + z.status" x-show="z.over_target" x-html="icon(z.status)"></span>
                                            <span :class="{ 'ccx-zero': !z.over_target }" x-text="dash(z.over_target)"></span>
                                        </span>
                                    </td>
                                    <td x-text="hm(z.median)"></td>
                                    <td x-text="hm(z.longest)"></td>
                                    <td x-text="`${z.arrivals_today} / ${z.departures_today}`"></td>
                                </tr>
                            </template>
                            <tr x-show="s.zones.length === 0">
                                <td colspan="7" style="text-align: center; padding: 2em" class="ccx-zero">
                                    No Emergency wards yet. Mark a ward type as Emergency Ward on the Ward Types page, and give the ED's zones that type.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot x-show="s.zones.length > 1">
                            <tr>
                                <td>All zones</td>
                                <td x-text="`${s.now.occupied}/${s.now.beds}`"></td>
                                <td x-text="fmt(s.now.free)"></td>
                                <td x-text="fmt(s.now.over_target)"></td>
                                <td x-text="hm(s.now.median)"></td>
                                <td x-text="hm(s.now.longest)"></td>
                                <td x-text="`${s.flow.arrivals_today} / ${s.flow.departures_today}`"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </article>

            <article class="ccx-card ccx-attention" aria-labelledby="cced-attention-title" x-show="cfg.attention.show">
                <header>
                    <h2 id="cced-attention-title">Needs attention</h2>
                    <span class="ccx-muted" x-text="(s.attention_total ? `${s.attention_total} · longest first` : '') + (tv && feedPages > 1 ? ` · page ${feedPage + 1} of ${feedPages}` : '')"></span>
                </header>
                <div class="ccx-feedwrap" x-ref="feed" :class="{ 'has-more': feedMore }" @scroll.passive="checkFeed()">
                <ol class="ccx-feed">
                    <template x-for="item in s.attention" :key="item.key">
                        <li class="ccx-item" :class="['is-' + item.severity, { 'is-changed': changed['item.' + item.key] }]">
                            <span class="ccx-ico" x-html="icon(item.severity)"></span>
                            <div>
                                <a :href="item.url" class="title"><span class="zone-dot" :class="item.colour ? 'is-' + item.colour : ''" aria-hidden="true"></span><span x-text="item.title"></span></a>
                                <p class="detail" x-text="item.detail"></p>
                            </div>
                            <span class="sev" x-text="item.minutes === null ? sevLabel(item.severity) : hm(item.minutes)"></span>
                        </li>
                    </template>
                </ol>
                <div class="ccx-empty" x-show="s.attention.length === 0">
                    <span class="ccx-ico is-good" x-html="icon('good')"></span>
                    <p><strong>Nothing needs attention</strong></p>
                    <p class="ccx-muted" x-text="`Everyone in the ED is within the ${hm(s.targets.minutes)} target, and no zone is full.`"></p>
                </div>
                </div>
                <p class="ccx-more" x-show="feedMore || s.attention_total > s.attention.length"
                    x-text="[feedMore ? 'Scroll for more' : '', s.attention_total > s.attention.length ? `${s.attention_total - s.attention.length} more not listed: see the zones` : ''].filter(Boolean).join(' · ')"></p>
            </article>
        </section>

        {{-- ------ Trends, and time in ED ------ --}}
        <section class="ccx-section ccx-trends">
            {{-- Arrivals by hour, today against yesterday --}}
            <article class="ccx-card ccx-census ccx-page" :class="{ 'is-current': tvPage === 'trends' }" x-show="cfg.trends.show">
                <header>
                    <h2>Arrivals by hour</h2>
                    <button type="button" class="ccx-toggle" @click="tables.hourly = !tables.hourly" x-text="tables.hourly ? 'Chart' : 'Table'" :aria-pressed="tables.hourly.toString()"></button>
                </header>
                <ul class="ccx-legend" style="list-style: none; padding: 0; margin: 0">
                    <li><span class="ccx-key"></span>Today</li>
                    <li><span class="ccx-key is-2" style="opacity: .55"></span>Yesterday</li>
                </ul>
                <div class="ccx-chartbody" x-show="tv || !tables.hourly">
                    <div class="ccx-plotwrap" @mouseleave="hover.hourly = null">
                        <template x-for="t in hourlyScale.ticks" :key="'h' + t">
                            <div class="ccx-grid" :class="{ 'is-base': t === 0 }" :style="`bottom: ${t / hourlyScale.max * 100}%`"><span x-text="fmt(t)"></span></div>
                        </template>
                        <div class="ccx-cols">
                            <template x-for="(y, i) in s.trend.hourly_yesterday" :key="'hb' + i">
                                <button type="button" @mouseenter="hover.hourly = i" @focus="hover.hourly = i" @blur="hover.hourly = null"
                                    :aria-label="`${hourLabel(i)}: ${i <= s.trend.hour_now ? s.trend.hourly_today[i] : 'not yet'} today, ${y} yesterday`">
                                    <span class="col">
                                        <span class="bar" x-show="i <= s.trend.hour_now" :style="`height: ${s.trend.hourly_today[i] / hourlyScale.max * 100}%`"></span>
                                    </span>
                                    <span class="col">
                                        <span class="bar is-ghost" :style="`height: ${y / hourlyScale.max * 100}%`"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                        <div class="ccx-tip" x-show="hover.hourly !== null" x-cloak :style="tipAt(hover.hourly, 24)">
                            <template x-if="hover.hourly !== null">
                                <div>
                                    <div class="k" x-text="hourLabel(hover.hourly)"></div>
                                    <div class="row" x-show="hover.hourly <= s.trend.hour_now"><span class="swatch"></span><span class="v" x-text="fmt(s.trend.hourly_today[hover.hourly])"></span><span class="k">today</span></div>
                                    <div class="row"><span class="swatch is-2"></span><span class="v" x-text="fmt(s.trend.hourly_yesterday[hover.hourly])"></span><span class="k">yesterday</span></div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="ccx-xaxis"><span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>23:00</span></div>
                </div>
                <div class="ccx-tablescroll" x-show="tables.hourly && !tv" x-cloak>
                    <table class="ccx-datatable">
                        <thead><tr><th scope="col">Hour</th><th scope="col">Today</th><th scope="col">Yesterday</th></tr></thead>
                        <tbody>
                            <template x-for="(y, i) in s.trend.hourly_yesterday" :key="'ht' + i">
                                <tr><td x-text="hourLabel(i)"></td><td x-text="i <= s.trend.hour_now ? fmt(s.trend.hourly_today[i]) : '–'"></td><td x-text="fmt(y)"></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </article>

            {{-- Arrivals and departures a day --}}
            <article class="ccx-card ccx-flowchart ccx-page is-later" :class="{ 'is-current': tvPage === 'trends' }" x-show="cfg.trends.show">
                <header>
                    <h2 x-text="`Arrivals & departures · ${s.trend.arrivals.length} days`"></h2>
                    <button type="button" class="ccx-toggle" @click="tables.daily = !tables.daily" x-text="tables.daily ? 'Chart' : 'Table'" :aria-pressed="tables.daily.toString()"></button>
                </header>
                <ul class="ccx-legend" style="list-style: none; padding: 0; margin: 0">
                    <li><span class="ccx-key"></span>Arrivals</li>
                    <li><span class="ccx-key is-2"></span>Left the ED</li>
                </ul>
                <div class="ccx-chartbody" x-show="tv || !tables.daily">
                    <div class="ccx-plotwrap" @mouseleave="hover.daily = null">
                        <template x-for="t in dailyScale.ticks" :key="'d' + t">
                            <div class="ccx-grid" :class="{ 'is-base': t === 0 }" :style="`bottom: ${t / dailyScale.max * 100}%`"><span x-text="fmt(t)"></span></div>
                        </template>
                        <div class="ccx-cols">
                            <template x-for="(a, i) in s.trend.arrivals" :key="'db' + i">
                                <button type="button" @mouseenter="hover.daily = i" @focus="hover.daily = i" @blur="hover.daily = null"
                                    :aria-label="`${s.trend.days[i]}: ${a} arrived, ${left(i)} left (${s.trend.admitted[i]} admitted, ${s.trend.home[i]} home)`">
                                    <span class="col">
                                        <span class="cap" x-show="i === s.trend.arrivals.length - 1" x-text="a"></span>
                                        <span class="bar" :style="`height: ${a / dailyScale.max * 100}%`"></span>
                                    </span>
                                    <span class="col">
                                        <span class="cap" x-show="i === s.trend.arrivals.length - 1" x-text="left(i)"></span>
                                        <span class="bar is-2" :style="`height: ${left(i) / dailyScale.max * 100}%`"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                        <div class="ccx-tip" x-show="hover.daily !== null" x-cloak :style="tipAt(hover.daily, s.trend.arrivals.length)">
                            <template x-if="hover.daily !== null">
                                <div>
                                    <div class="k" x-text="s.trend.days[hover.daily] + (hover.daily === s.trend.arrivals.length - 1 ? ' · so far' : '')"></div>
                                    <div class="row"><span class="swatch"></span><span class="v" x-text="fmt(s.trend.arrivals[hover.daily])"></span><span class="k">arrived</span></div>
                                    <div class="row"><span class="swatch is-2"></span><span class="v" x-text="fmt(left(hover.daily))"></span><span class="k" x-text="`left · ${s.trend.admitted[hover.daily]} admitted, ${s.trend.home[hover.daily]} home`"></span></div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="ccx-xaxis"><span x-text="s.trend.labels[0]"></span><span x-text="s.trend.labels[Math.floor(s.trend.labels.length / 2)]"></span><span>Today</span></div>
                </div>
                <div class="ccx-tablescroll" x-show="tables.daily && !tv" x-cloak>
                    <table class="ccx-datatable">
                        <thead><tr><th scope="col">Day</th><th scope="col">Arrived</th><th scope="col">Admitted</th><th scope="col">Home</th></tr></thead>
                        <tbody>
                            <template x-for="(a, i) in s.trend.arrivals" :key="'dt' + i">
                                <tr><td x-text="s.trend.days[i]"></td><td x-text="fmt(a)"></td><td x-text="fmt(s.trend.admitted[i])"></td><td x-text="fmt(s.trend.home[i])"></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </article>

            {{-- Time in ED now, by band --}}
            <article class="ccx-card ccx-acuity ccx-page" :class="{ 'is-current': tvPage === 'time' }" x-show="cfg.time.show">
                <header>
                    <h2>Time in ED now</h2>
                    <span class="ccx-muted" x-text="`${fmt(s.now.known)} patients since they arrived · target ${hm(s.targets.minutes)}`"></span>
                </header>
                <div class="ccx-hbars">
                    <template x-for="b in s.buckets" :key="b.label">
                        <div class="ccx-hbar">
                            <span class="label" x-text="b.label"></span>
                            <span class="value"><span x-text="fmt(b.count)"></span><small x-text="s.now.known ? pct(b.count / s.now.known * 100) : ''"></small></span>
                            <div class="track" aria-hidden="true"><div class="fill" :class="{ 'is-over': b.over_target }" :style="`width: ${bucketMax ? b.count / bucketMax * 100 : 0}%`"></div></div>
                        </div>
                    </template>
                </div>
            </article>
        </section>

        <p class="ccx-foot">
            The zones are the wards of an Emergency ward type (Ward Types). Time in the ED runs from each patient's arrival, C+'s
            census Entry Date for the ED, against a target of <span x-text="hm(s.targets.minutes)"></span> set in Settings.
            Arrivals and departures come from the admission log; a departure that went on to a ward counts as admitted.
            Doctors are each patient's attending doctor. No patient is named on this screen.
        </p>
        </div>

        @include('command-center-ed.partials.settings')
    </div>

    <script type="application/json" id="cced-snapshot">@json($snapshot)</script>

    @verbatim
    <script>
        document.addEventListener('alpine:init', () => {
            const ICONS = {
                critical: '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="9" fill="currentColor"/><path d="M10 5.3v6M10 14.4v.3" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>',
                serious: '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.2 18.6 17.6H1.4Z" fill="currentColor" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 7.6v4.6M10 15v.3" stroke="#1a1a19" stroke-width="2" stroke-linecap="round"/></svg>',
                warning: '<svg viewBox="0 0 20 20" aria-hidden="true"><rect x="4" y="4" width="12" height="12" rx="2" transform="rotate(45 10 10)" fill="currentColor"/><path d="M10 6.4v4.6M10 13.6v.3" stroke="#1a1a19" stroke-width="2" stroke-linecap="round"/></svg>',
                good: '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="9" fill="currentColor"/><path d="m6.2 10.3 2.6 2.6 5-5.4" fill="none" stroke="#fff" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            };
            const DASH = '—';
            const missing = (n) => n === null || n === undefined;

            // TV mode's pages, in turn beside the attention list
            const TV_PAGES = [
                { key: 'zones', label: 'Zones' },
                { key: 'trends', label: 'Trends' },
                { key: 'time', label: 'Time in ED' },
            ];
            const STEP_SECONDS = 15;
            const FEED_SECONDS = 10;

            // ------ Display settings (partials/settings), kept in this browser so each screen has its own.
            // The time-in-ED target is not one of them: it is the hospital's, saved on the server ------
            const SETTINGS_KEY = 'ccedDisplay';
            const TEXT_RATIO = 0.016;
            const SCREENS = [
                { key: 'auto', label: 'Auto', sub: 'Fits the window' },
                { key: 'hd', label: '720p', sub: 'HD · 1280 × 720', height: 720 },
                { key: 'fhd', label: '1080p', sub: 'Full HD · 1920 × 1080', height: 1080 },
                { key: 'qhd', label: '2K', sub: 'QHD · 2560 × 1440', height: 1440 },
                { key: 'uhd', label: '4K', sub: 'UHD · 3840 × 2160', height: 2160 },
            ];
            const SIZE = { min: 50, max: 250 };
            const SECTION_SIZE = { min: 50, max: 200 };
            const ROWS = [3, 4, 5, 6, 7, 8, 9, 10, 12, 14, 16];
            const SECTIONS = [
                { key: 'kpis', label: 'Headline figures', about: 'The four cards across the top' },
                { key: 'zones', label: 'Zones', about: 'Every ED zone: beds, time in the ED, in and out today', perPage: 'Zones per page', pageSizes: ROWS, seconds: true },
                { key: 'trends', label: 'Trends', about: 'Arrivals by hour, and arrivals and departures a day', seconds: true },
                { key: 'time', label: 'Time in ED', about: 'Patients in the ED now, by time since they arrived', seconds: true },
                { key: 'attention', label: 'Needs attention', about: 'The list beside the pages', perPage: 'Items per page', pageSizes: [2, 3, 4, 5, 6, 8, 10, 12], seconds: true },
            ];
            // The shared board styles size each section by these variables
            const SIZE_VAR = { kpis: 'kpis', zones: 'wards', trends: 'trends', time: 'acuity', attention: 'attention' };
            const SECONDS = [5, 8, 10, 15, 20, 30, 45, 60, 90, 120];
            const defaults = () => ({
                screen: 'auto',
                size: 100,
                kpis: { show: true, size: 100 },
                zones: { show: true, size: 100, perPage: 0, seconds: STEP_SECONDS },
                trends: { show: true, size: 100, seconds: STEP_SECONDS },
                time: { show: true, size: 100, seconds: STEP_SECONDS },
                attention: { show: true, size: 100, perPage: 0, seconds: FEED_SECONDS },
            });
            const readSettings = (saved) => {
                const cfg = defaults();
                if (!saved || typeof saved !== 'object') return cfg;
                const whole = (value, { min, max }, fallback) => (Number.isFinite(value) ? Math.min(max, Math.max(min, Math.round(value))) : fallback);
                if (SCREENS.some((screen) => screen.key === saved.screen)) cfg.screen = saved.screen;
                cfg.size = whole(saved.size, SIZE, cfg.size);
                SECTIONS.forEach((section) => {
                    const from = saved[section.key] || {};
                    const to = cfg[section.key];
                    if (typeof from.show === 'boolean') to.show = from.show;
                    to.size = whole(from.size, SECTION_SIZE, to.size);
                    if (section.perPage && [0, ...section.pageSizes].includes(from.perPage)) to.perPage = from.perPage;
                    if (section.seconds && SECONDS.includes(from.seconds)) to.seconds = from.seconds;
                });
                if (!TV_PAGES.some((page) => cfg[page.key].show)) cfg.zones.show = true;
                return cfg;
            };

            // A clean axis for counts: about four whole-number steps, the top at or above the highest value
            const scale = (highest) => {
                const rough = Math.max(highest, 1) / 4;
                const exp = Math.pow(10, Math.floor(Math.log10(rough)));
                const step = [1, 2, 5, 10].map((m) => m * exp).find((s) => s >= rough && Number.isInteger(s)) ?? 10 * exp;
                const max = Math.max(step, Math.ceil(highest / step) * step);
                const ticks = [];
                for (let t = 0; t <= max; t += step) ticks.push(t);
                return { max, ticks };
            };

            window.Alpine.data('commandCenterEd', () => ({
                s: JSON.parse(document.getElementById('cced-snapshot').textContent),
                url: null,
                settingsUrl: null,
                connection: 'live',
                loading: false,
                lastOk: Date.now(),
                now: new Date(),
                cycle: 0,
                timer: null,
                theme: 'dark',
                tv: false,
                changed: {},
                hover: { hourly: null, daily: null },
                tables: { hourly: false, daily: false },
                feedMore: false,
                cfg: defaults(),
                settingsOpen: false,
                dpr: 1,
                screenPx: { width: 0, height: 0 },
                screens: SCREENS,
                sections: SECTIONS,
                secondsOptions: SECONDS,
                fits: { zones: 0 },
                zonesFit: 1,
                // The time-in-ED target in the settings: saving, and any error
                targetSaving: false,
                targetError: '',
                paused: false,
                stepSeconds: STEP_SECONDS,
                tvPage: 'zones',
                subPage: 0,
                subPages: 1,
                feedPage: 0,
                feedPages: 1,
                stepLeft: STEP_SECONDS,
                stepKey: 0,
                feedTick: 0,

                init() {
                    this.url = this.$el.dataset.url;
                    this.settingsUrl = this.$el.dataset.settingsUrl;
                    try { this.theme = localStorage.getItem('ccedTheme') === 'light' ? 'light' : 'dark'; } catch (e) {}
                    try { this.tv = localStorage.getItem('ccedTv') === 'on'; } catch (e) {}
                    try { this.cfg = readSettings(JSON.parse(localStorage.getItem(SETTINGS_KEY))); } catch (e) {}
                    this.$watch('cfg', () => this.settingsChanged());
                    this.measureScreen();
                    window.addEventListener('resize', () => this.measureScreen());
                    if (this.tv) this.$nextTick(() => this.announceTv(true));

                    setInterval(() => {
                        this.now = new Date();
                        this.rotate();
                    }, 1000);
                    this.schedule();
                    this.$nextTick(() => this.checkFeed());
                    if (window.ResizeObserver) {
                        const resized = new ResizeObserver(() => this.refit());
                        [this.$refs.feed, this.$refs.zones].forEach((box) => resized.observe(box));
                    }
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible' && Date.now() - this.lastOk >= this.s.refresh_seconds * 1000) this.load();
                    });
                    document.addEventListener('fullscreenchange', () => {
                        if (!document.fullscreenElement && this.tv) this.setTv(false);
                    });
                },

                schedule() {
                    clearTimeout(this.timer);
                    this.cycle++;
                    this.timer = setTimeout(() => this.load(), this.s.refresh_seconds * 1000);
                },

                async load() {
                    if (this.loading || this.connection === 'signed-out') return;
                    if (document.visibilityState === 'hidden') return this.schedule();
                    this.loading = true;
                    try {
                        const response = await fetch(this.url, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                            cache: 'no-store',
                        });
                        if ([401, 403, 419].includes(response.status)) {
                            this.connection = 'signed-out';
                            return;
                        }
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        this.take(await response.json());
                    } catch (e) {
                        this.connection = 'retrying';
                    } finally {
                        this.loading = false;
                        if (this.connection !== 'signed-out') this.schedule();
                    }
                },

                take(next) {
                    this.markChanges(this.s, next);
                    this.s = next;
                    this.connection = 'live';
                    this.lastOk = Date.now();
                    this.$nextTick(() => this.refit());
                },

                // Briefly light up what the latest figures changed
                markChanges(before, after) {
                    const sign = (value) => JSON.stringify(value);
                    const flash = (key) => {
                        this.changed[key] = false;
                        this.$nextTick(() => {
                            this.changed[key] = true;
                            setTimeout(() => { this.changed[key] = false; }, 2500);
                        });
                    };
                    const cards = {
                        now: (s) => ({ patients: s.now.patients, occupied: s.now.occupied, waiting: s.now.waiting, free: s.now.free }),
                        time: (s) => ({ within: s.now.within_target, over: s.now.over_target, target: s.targets.minutes }),
                        flow: (s) => s.flow,
                        doctors: (s) => s.doctors,
                    };
                    for (const [key, pick] of Object.entries(cards)) {
                        if (sign(pick(before)) !== sign(pick(after))) flash(key);
                    }
                    const zone = (z) => sign({ occupied: z.occupied, over: z.over_target, arrivals: z.arrivals_today, departures: z.departures_today });
                    const was = Object.fromEntries(before.zones.map((z) => [z.id, zone(z)]));
                    after.zones.forEach((z) => { if (z.id in was && was[z.id] !== zone(z)) flash('zone.' + z.id); });
                    const listed = new Set(before.attention.map((item) => item.key));
                    after.attention.forEach((item) => { if (!listed.has(item.key)) flash('item.' + item.key); });
                },

                // ------ The time-in-ED target: the hospital's, saved on the server for every screen ------
                async saveTarget(minutes) {
                    minutes = Number(minutes);
                    if (minutes === this.s.targets.minutes || this.targetSaving) return;
                    this.targetSaving = true;
                    this.targetError = '';
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                        const response = await fetch(this.settingsUrl, {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ target_minutes: minutes }),
                        });
                        if (!response.ok) throw new Error(response.status === 403 ? 'You cannot change the target.' : 'Not saved (HTTP ' + response.status + '). Try again.');
                        this.take(await response.json());
                    } catch (e) {
                        this.targetError = e.message || 'Not saved. Try again.';
                    } finally {
                        this.targetSaving = false;
                    }
                },

                toggleTheme() {
                    this.theme = this.theme === 'dark' ? 'light' : 'dark';
                    try { localStorage.setItem('ccedTheme', this.theme); } catch (e) {}
                },

                setTv(on) {
                    this.tv = on;
                    try { localStorage.setItem('ccedTv', on ? 'on' : 'off'); } catch (e) {}
                    this.announceTv(on);
                    if (on && !document.fullscreenElement && document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen().catch(() => {});
                    } else if (!on && document.fullscreenElement && document.exitFullscreen) {
                        document.exitFullscreen().catch(() => {});
                    }
                },

                // The app layout hides its menu and footer on this event
                announceTv(on) {
                    window.dispatchEvent(new CustomEvent('toggle-custom-fullscreen', { detail: { enabled: on } }));
                    this.tvPage = this.tvPages[0].key;
                    this.subPage = this.feedPage = this.feedTick = 0;
                    this.paused = false;
                    this.restartStep();
                    setTimeout(() => {
                        [this.$refs.zones, this.$refs.feed].forEach((box) => { box.scrollTop = 0; });
                        this.refit();
                    }, 300);
                },

                checkFeed() {
                    const feed = this.$refs.feed;
                    this.feedMore = !this.tv && !!feed && feed.scrollHeight - feed.scrollTop - feed.clientHeight > 4;
                },

                refit() {
                    this.checkFeed();
                    if (this.tv) {
                        this.fitZones();
                        this.goSubPage(this.subPage);
                        this.goFeedPage(this.feedPage);
                    }
                },

                // TV mode, every second: the zones, trends and time in ED take turns, and the attention list pages on its own
                rotate() {
                    if (!this.tv || this.paused || this.settingsOpen) return;
                    if (--this.stepLeft <= 0) this.nextStep();
                    if (++this.feedTick >= this.cfg.attention.seconds) {
                        this.feedTick = 0;
                        this.goFeedPage(this.feedPage + 1);
                    }
                },
                nextStep() {
                    if (this.subPage + 1 < this.subPages) {
                        this.goSubPage(this.subPage + 1);
                    } else {
                        const pages = this.tvPages;
                        const at = pages.findIndex((page) => page.key === this.tvPage);
                        this.showTv(pages[(at + 1) % pages.length].key);
                        return;
                    }
                    this.restartStep();
                },
                showTv(page) {
                    this.tvPage = page;
                    this.subPage = 0;
                    this.$nextTick(() => this.goSubPage(0));
                    this.restartStep();
                },
                restartStep() {
                    this.stepSeconds = this.cfg[this.tvPage]?.seconds || STEP_SECONDS;
                    this.stepLeft = this.stepSeconds;
                    this.stepKey++;
                },

                // One screenful of the zone table's rows, when they do not all fit
                goSubPage(page) {
                    const box = this.tvPage === 'zones' ? this.$refs.zones : null;
                    if (!box || box.offsetParent === null) {
                        this.subPage = 0;
                        this.subPages = 1;
                        return;
                    }
                    const rows = [...box.querySelectorAll('tbody tr[data-row]')];
                    const foot = box.querySelector('tfoot');
                    const bottom = foot && foot.offsetParent !== null ? foot.offsetHeight : 0;
                    const pages = this.pages(box, rows, bottom, this.shareRows(box, rows, bottom));
                    this.subPages = pages.length;
                    this.subPage = Math.min(page, pages.length - 1);
                    this.turnTo(box, rows, pages[this.subPage]);
                },
                shareRows(box, rows, bottom) {
                    const limit = this.cfg.zones.perPage;
                    box.style.removeProperty('--ccx-row');
                    if (!rows.length) return limit;
                    const origin = box.getBoundingClientRect().top - box.scrollTop;
                    const view = box.clientHeight - (rows[0].getBoundingClientRect().top - origin) - bottom;
                    const natural = this.pages(box, rows, bottom);
                    const height = rows.reduce((sum, row) => sum + row.getBoundingClientRect().height, 0) / rows.length;
                    this.fits.zones = natural.length > 1 ? natural[0].rows.length : Math.max(rows.length, Math.floor((view + 1) / height));
                    box.style.setProperty('--ccx-row', Math.floor(view / Math.min(limit || Infinity, this.fits.zones)) + 'px');
                    return limit;
                },
                // A zone table wider than its card is scaled down until every column fits
                fitZones() {
                    const box = this.$refs.zones;
                    box.style.removeProperty('--ccx-fit');
                    this.zonesFit = 1;
                    if (box.offsetParent === null) return;
                    const over = box.querySelector('table').offsetWidth / box.clientWidth;
                    if (over > 1.005) {
                        this.zonesFit = Math.floor(100 / over) / 100;
                        box.style.setProperty('--ccx-fit', this.zonesFit);
                    }
                },
                goFeedPage(page) {
                    const box = this.$refs.feed;
                    if (box.offsetParent === null) return;
                    const rows = [...box.querySelectorAll('.ccx-item')];
                    const pages = this.pages(box, rows, 0, this.cfg.attention.perPage);
                    this.feedPages = pages.length;
                    this.feedPage = page % pages.length;
                    this.turnTo(box, rows, pages[this.feedPage]);
                },
                pages(box, rows, bottom = 0, limit = 0) {
                    if (!rows.length) return [{ start: 0, rows: [] }];
                    const origin = box.getBoundingClientRect().top - box.scrollTop;
                    const first = rows[0].getBoundingClientRect().top - origin;
                    const view = box.clientHeight - first - bottom;
                    const pages = [];
                    let pageTop = null;
                    for (const row of rows) {
                        const rect = row.getBoundingClientRect();
                        const at = rect.top - origin;
                        const full = limit > 0 && pages.length > 0 && pages[pages.length - 1].rows.length >= limit;
                        if (pageTop === null || full || at + rect.height > pageTop + view + 1) {
                            pageTop = at;
                            pages.push({ start: Math.round(at - first), rows: [] });
                        }
                        pages[pages.length - 1].rows.push(row);
                    }
                    return pages;
                },
                turnTo(box, rows, page) {
                    const shown = new Set(page.rows);
                    rows.forEach((row) => row.classList.toggle('is-off', !shown.has(row)));
                    if (box.scrollTop === page.start) return;
                    box.scrollTop = page.start;
                    page.rows.forEach((row) => row.animate?.([{ opacity: 0 }, { opacity: 1 }], { duration: 450, easing: 'ease-out' }));
                },

                // ------ Display settings (partials/settings) ------
                openSettings() {
                    const dialog = this.$refs.settings;
                    this.settingsOpen = true;
                    this.targetError = '';
                    if (dialog.open) return;
                    if (dialog.showModal) dialog.showModal();
                    else dialog.setAttribute('open', '');
                },
                closeSettings() {
                    const dialog = this.$refs.settings;
                    if (dialog.open && dialog.close) dialog.close();
                    else dialog.removeAttribute('open');
                    this.settingsClosed();
                },
                settingsClosed() {
                    if (!this.settingsOpen) return;
                    this.settingsOpen = false;
                    this.restartStep();
                },
                settingsChanged() {
                    try { localStorage.setItem(SETTINGS_KEY, JSON.stringify(this.cfg)); } catch (e) {}
                    if (!this.tvPages.some((page) => page.key === this.tvPage)) this.showTv(this.tvPages[0].key);
                    this.$nextTick(() => this.refit());
                },
                preview(key) {
                    if (this.tv && key !== this.tvPage && this.tvPages.some((page) => page.key === key)) this.showTv(key);
                },
                resetSettings() {
                    this.cfg = defaults();
                },
                nudgeSize(step) {
                    this.cfg.size = Math.min(SIZE.max, Math.max(SIZE.min, this.cfg.size + step));
                },
                nudgeSection(key, step) {
                    const section = this.cfg[key];
                    section.size = Math.min(SECTION_SIZE.max, Math.max(SECTION_SIZE.min, section.size + step));
                },
                canHide(key) {
                    return !TV_PAGES.some((page) => page.key === key) || this.tvPages.length > 1;
                },
                toggleSection(key) {
                    const section = this.cfg[key];
                    if (!section.show || this.canHide(key)) section.show = !section.show;
                },

                get tvPages() {
                    return TV_PAGES.filter((page) => this.cfg[page.key].show);
                },
                get boardStyle() {
                    const { cfg } = this;
                    const preset = SCREENS.find((screen) => screen.key === cfg.screen);
                    const vars = [`--ccx-size: ${cfg.size / 100}`, ...SECTIONS.map((section) => `--ccx-${SIZE_VAR[section.key]}: ${cfg[section.key].size / 100}`)];
                    if (preset && preset.height) vars.push(`--ccx-base: ${(preset.height * TEXT_RATIO / this.dpr).toFixed(2)}px`);
                    return vars.join('; ');
                },
                measureScreen() {
                    this.dpr = window.devicePixelRatio || 1;
                    this.screenPx = {
                        width: Math.round(Math.max(window.screen.width || 0, window.innerWidth) * this.dpr),
                        height: Math.round(Math.max(window.screen.height || 0, window.innerHeight) * this.dpr),
                    };
                },
                get detectedScreen() {
                    const { width, height } = this.screenPx;
                    const short = Math.min(width, height);
                    const nearest = SCREENS.filter((screen) => screen.height)
                        .reduce((best, screen) => (Math.abs(screen.height - short) < Math.abs(best.height - short) ? screen : best));
                    return { width, height, key: nearest.key, scaling: Math.round(this.dpr * 100) };
                },
                get screenNote() {
                    const { width, height, scaling } = this.detectedScreen;
                    return `This screen is ${width} × ${height}` + (scaling === 100 ? '.' : `, with display scaling at ${scaling}%.`);
                },
                fitNote(key) {
                    if (!this.tv) return 'Takes effect in TV mode';
                    const fit = this.fits[key];
                    if (!fit) return '';
                    return this.cfg[key].perPage > fit ? `Only ${fit} fit at this size` : `${fit} fit at this size`;
                },
                get zonesFitNote() {
                    return this.tv && this.zonesFit < 1 ? `Shrunk to ${Math.round(this.zonesFit * 100)}% so every column fits.` : '';
                },

                get connectionLabel() {
                    return { live: 'Live', retrying: 'Reconnecting…', 'signed-out': 'Signed out' }[this.connection];
                },
                get clockTime() {
                    return String(this.now.getHours()).padStart(2, '0') + ':' + String(this.now.getMinutes()).padStart(2, '0');
                },
                get clockSeconds() {
                    return ':' + String(this.now.getSeconds()).padStart(2, '0');
                },
                get clockDate() {
                    return this.now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                },

                get hourlyScale() {
                    return scale(Math.max(0, ...this.s.trend.hourly_today, ...this.s.trend.hourly_yesterday));
                },
                get dailyScale() {
                    return scale(Math.max(0, ...this.s.trend.arrivals, ...this.s.trend.arrivals.map((_, i) => this.left(i))));
                },
                get bucketMax() {
                    return Math.max(0, ...this.s.buckets.map((b) => b.count));
                },
                left(i) {
                    return (this.s.trend.admitted[i] || 0) + (this.s.trend.home[i] || 0);
                },
                band(i, n) {
                    return (i + 0.5) / n * 100;
                },
                tipAt(i, n) {
                    if (i === null) return '';
                    const x = this.band(i, n);
                    return `left: ${x}%; transform: translateX(${x < 30 ? '-8%' : x > 70 ? '-92%' : '-50%'})`;
                },
                hourLabel(h) {
                    return String(h).padStart(2, '0') + ':00–' + String(h).padStart(2, '0') + ':59';
                },

                icon(severity) {
                    return ICONS[severity] || ICONS.good;
                },
                sevLabel(severity) {
                    return { critical: 'Critical', serious: 'Serious', warning: 'Watch', good: 'OK' }[severity] || '';
                },
                occupancyLabel(status) {
                    return { good: 'Room to spare', warning: 'Busy', critical: 'Full' }[status] || '';
                },
                timeLabel(status) {
                    return { good: 'Within target', warning: 'Over target', serious: 'Well over target', critical: 'Far over target' }[status] || '';
                },

                fmt(n) {
                    return missing(n) ? DASH : Number(n).toLocaleString('en-US');
                },
                dec(n) {
                    return missing(n) ? DASH : Number(n).toLocaleString('en-US', { maximumFractionDigits: 1 });
                },
                pct(n) {
                    return missing(n) ? DASH : this.dec(n) + '%';
                },
                dash(n) {
                    return n ? this.fmt(n) : '–';
                },
                // Minutes as the board says them: "45 min", "2 h", "2 h 05"
                hm(n) {
                    if (missing(n)) return DASH;
                    const m = Math.round(n);
                    if (m < 60) return m + ' min';
                    const rest = m % 60;
                    return Math.floor(m / 60) + ' h' + (rest ? ' ' + String(rest).padStart(2, '0') : '');
                },
                versus(now, before, when) {
                    if (missing(now) || missing(before)) return '';
                    const d = now - before;
                    return d === 0 ? `Same as ${when}` : `${d > 0 ? '▲' : '▼'} ${Math.abs(d)} vs ${when}`;
                },
            }));
        });
    </script>
    @endverbatim
</x-app-layout>
