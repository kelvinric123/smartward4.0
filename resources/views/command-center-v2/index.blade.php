<x-app-layout>
    @include('command-center-v2.partials.styles')

    <div class="ccx" data-theme="dark" x-data="commandCenterV2" data-url="{{ route('command-center-v2.data') }}"
        :data-theme="theme" :class="{ 'is-tv': tv, 'no-kpis': !cfg.kpis.show, 'no-attention': !cfg.attention.show }" :style="boardStyle"
        @keydown.escape.window="tv && !settingsOpen && setTv(false)">
        <script>
            // The theme this screen was left on, before the first paint
            try { if (localStorage.getItem('ccv2Theme') === 'light') document.currentScript.parentElement.dataset.theme = 'light'; } catch (e) {}
        </script>

        <div class="ccx-progress" aria-hidden="true">
            <template x-for="c in [cycle]" :key="c">
                <span :class="{ 'is-stopped': connection !== 'live' }" :style="`animation-duration: ${s.refresh_seconds}s`"></span>
            </template>
        </div>

        {{-- ------ Header: whose board, how fresh, the time ------ --}}
        <header class="ccx-top">
            <div class="ccx-brand">
                <span class="ccx-logo"><img src="{{ $logoUrl }}" alt="{{ $hospital?->name ?? 'Hospital' }} logo"></span>
                <div style="min-width: 0">
                    <p class="ccx-eyebrow">{{ $hospital?->name ?? config('app.name') }}</p>
                    <h1>Command Center V2</h1>
                    <p class="ccx-sub">Executive overview &middot; every ward, live</p>
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
                <a href="{{ route('command-center.index') }}" class="ccx-btn" x-show="!tv" title="The Command Center: every patient needing attention, and the period analytics">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" /></svg>
                    Operations view
                </a>
                <button type="button" class="ccx-btn" @click="openSettings()" aria-haspopup="dialog" title="This screen's size, and each section's size, pages and timing">
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
        {{-- ------ The four areas management answers for ------ --}}
        <section class="ccx-section ccx-kpis" aria-label="Key figures" x-show="cfg.kpis.show">
            {{-- Capacity --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.capacity }">
                <header>
                    <h2>Bed occupancy</h2>
                    <span class="ccx-chip" :class="'is-' + (s.capacity.status || 'good')" x-show="s.capacity.status">
                        <span class="ccx-ico" x-html="icon(s.capacity.status)"></span>
                        <span x-text="capacityLabel(s.capacity.status)"></span>
                    </span>
                </header>
                <div class="ccx-hero">
                    <span class="num" x-text="s.capacity.occupancy === null ? '—' : dec(s.capacity.occupancy) + '%'"></span>
                    <span class="cap" x-text="`${fmt(s.capacity.occupied)} of ${fmt(s.capacity.beds)} beds occupied`"></span>
                </div>
                <div class="ccx-meter" :class="'is-' + (s.capacity.status || 'good')" role="img"
                    :aria-label="`Occupancy ${dec(s.capacity.occupancy)}% against a target of ${s.targets.occupancy}%`">
                    <span class="fill" :style="`width: ${Math.min(100, s.capacity.occupancy || 0)}%`"></span>
                    <span class="tick" :style="`left: ${s.targets.occupancy}%`"></span>
                </div>
                <div class="ccx-meter-legend"><span>0%</span><span x-text="`Target ${s.targets.occupancy}% or under`"></span><span>100%</span></div>
                <dl class="ccx-minis">
                    <div><dt>Free beds</dt><dd x-text="fmt(s.capacity.free)"></dd></div>
                    <div><dt>Prebooked</dt><dd x-text="fmt(s.capacity.incoming)"></dd></div>
                    <div><dt>To discharge</dt><dd x-text="fmt(s.capacity.pending_discharge)"></dd></div>
                </dl>
                <p class="ccx-muted" style="margin-top: .75em" x-show="s.capacity.critical_care"
                    x-text="s.capacity.critical_care ? `Critical care: ${s.capacity.critical_care.occupied} of ${s.capacity.critical_care.beds} beds · ${s.capacity.critical_care.ventilated} ventilated` : ''"></p>
            </article>

            {{-- Patient flow --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.flow }">
                <header>
                    <h2>Patient flow today</h2>
                    <span class="ccx-muted">Since midnight</span>
                </header>
                <div class="ccx-pair">
                    <div>
                        <p class="lbl">Admissions</p>
                        <p class="num" x-text="fmt(s.flow.admissions_today)"></p>
                        <p class="ccx-delta" x-text="versus(s.flow.admissions_today, s.flow.admissions_same_time_yesterday, 'same time yesterday')"></p>
                    </div>
                    <div>
                        <p class="lbl">Discharges</p>
                        <p class="num" x-text="fmt(s.flow.discharges_today)"></p>
                        <p class="ccx-delta" x-text="versus(s.flow.discharges_today, s.flow.discharges_same_time_yesterday, 'same time yesterday')"></p>
                    </div>
                </div>
                <dl class="ccx-rows">
                    <div>
                        <dt x-text="`Discharged by ${hour(s.targets.noon_hour)}`"></dt>
                        <dd><span x-text="pct(s.flow.discharged_by_noon_pct)"></span><small x-text="`${s.flow.discharged_by_noon} of ${s.flow.discharges_today}`"></small></dd>
                    </div>
                    <div>
                        <dt x-text="`By ${hour(s.targets.noon_hour)}, last ${s.targets.los_days} days`"></dt>
                        <dd><span x-text="pct(s.flow.discharged_by_noon_pct_period)"></span><small x-text="`of ${fmt(s.flow.discharges_period)}`"></small></dd>
                    </div>
                    <div>
                        <dt x-text="`Average stay, last ${s.targets.los_days} days`"></dt>
                        <dd>
                            <span x-text="days(s.flow.alos)"></span>
                            <small class="ccx-delta" style="display: inline" :class="alosTone" x-text="alosChange"></small>
                        </dd>
                    </div>
                </dl>
            </article>

            {{-- Quality & safety --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.quality }">
                <header>
                    <h2>Quality &amp; safety</h2>
                    <span class="ccx-chip" :class="'is-' + s.quality.status">
                        <span class="ccx-ico" x-html="icon(s.quality.status)"></span>
                        <span x-text="qualityLabel(s.quality.status)"></span>
                    </span>
                </header>
                <div class="ccx-hero">
                    <span class="num is-mid" x-text="fmt(s.quality.needs_attention)"></span>
                    <span class="cap" x-text="`of ${fmt(s.quality.inpatients)} inpatients need attention now`"></span>
                </div>
                <dl class="ccx-rows">
                    <div>
                        <dt><span class="ccx-ico is-serious" x-show="s.quality.ews_urgent" x-html="icon('serious')"></span>EWS 5 or more</dt>
                        <dd><span x-text="fmt(s.quality.ews_urgent)"></span><small x-text="`${fmt(s.quality.ews_warning)} at 3–4`"></small></dd>
                    </div>
                    <div>
                        <dt><span class="ccx-ico is-serious" x-show="s.quality.escalations" x-html="icon('serious')"></span>Monitor escalations</dt>
                        <dd x-text="fmt(s.quality.escalations)"></dd>
                    </div>
                    <div>
                        <dt><span class="ccx-ico is-warning" x-show="s.quality.overdue_care" x-html="icon('warning')"></span>Care overdue</dt>
                        <dd><span x-text="fmt(s.quality.overdue_care)"></span><small>patients</small></dd>
                    </div>
                    <div>
                        <dt><span class="ccx-ico is-warning" x-show="s.quality.obs_due > s.quality.obs_done" x-html="icon('warning')"></span><span x-text="`Vital signs in ${s.targets.obs_hours} h`"></span></dt>
                        <dd><span x-text="pct(s.quality.obs_pct)"></span><small x-text="`${fmt(s.quality.obs_done)} of ${fmt(s.quality.obs_due)}`"></small></dd>
                    </div>
                    <div>
                        <dt><span class="ccx-ico" :class="s.quality.alerts.waiting_urgent ? 'is-critical' : 'is-warning'" x-show="s.quality.alerts.waiting" x-html="icon(s.quality.alerts.waiting_urgent ? 'critical' : 'warning')"></span>Alerts waiting</dt>
                        <dd><span x-text="fmt(s.quality.alerts.pending)"></span><small x-text="`${fmt(s.quality.alerts.urgent)} urgent`"></small></dd>
                    </div>
                    <div>
                        <dt>Median response today</dt>
                        <dd><span x-text="mins(s.quality.alerts.median_today)"></span><small x-text="`7-day ${mins(s.quality.alerts.median_week)}`"></small></dd>
                    </div>
                </dl>
            </article>

            {{-- Workforce --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.workforce }">
                <header>
                    <h2>Nurses on shift</h2>
                    <span class="ccx-chip" :class="'is-' + (s.workforce.status || 'good')" x-show="s.workforce.status">
                        <span class="ccx-ico" x-html="icon(s.workforce.status)"></span>
                        <span x-text="workforceLabel(s.workforce.status)"></span>
                    </span>
                </header>
                <template x-if="s.workforce.rostered_wards > 0">
                    <div>
                        <div class="ccx-hero">
                            <span class="num" x-text="dec(s.workforce.ratio)"></span>
                            <span class="cap">patients per nurse on duty</span>
                        </div>
                        <div class="ccx-meter" :class="'is-' + (s.workforce.coverage !== null && s.workforce.coverage < 100 ? 'serious' : 'good')" role="img"
                            :aria-label="`${s.workforce.on_duty} nurses on duty against a minimum of ${s.workforce.minimum}`">
                            <span class="fill" :style="`width: ${Math.min(100, s.workforce.coverage || 0)}%`"></span>
                        </div>
                        <div class="ccx-meter-legend"><span x-text="`${pct(s.workforce.coverage)} of minimum staffing`"></span><span x-text="s.workforce.shifts.join(' · ') + ' shift'"></span></div>
                        <dl class="ccx-rows">
                            <div><dt>Nurses on duty</dt><dd><span x-text="fmt(s.workforce.on_duty)"></span><small x-text="`minimum ${fmt(s.workforce.minimum)}`"></small></dd></div>
                            <div><dt>Wards below minimum</dt><dd><span x-text="fmt(s.workforce.below_minimum)"></span><small x-text="`of ${fmt(s.workforce.rostered_wards)} rostered`"></small></dd></div>
                            <div><dt>Wards without a roster</dt><dd x-text="fmt(s.workforce.wards - s.workforce.rostered_wards)"></dd></div>
                        </dl>
                    </div>
                </template>
                <template x-if="s.workforce.rostered_wards === 0">
                    <div class="ccx-empty">
                        <p>No ward has a roster for the shift on now.</p>
                        <a href="{{ route('ward.ai-schedule') }}" class="ccx-link">Plan one in the AI Nurse Schedule</a>
                    </div>
                </template>
            </article>
        </section>

        {{-- ------ TV mode: the page up beside the attention list, and how long it has left ------ --}}
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

        {{-- ------ Every ward, and what needs attention ------ --}}
        <section class="ccx-section ccx-mid">
            <article class="ccx-card ccx-wards ccx-page" :class="{ 'is-current': tvPage === 'wards' }" x-show="cfg.wards.show">
                <header>
                    <h2>Wards</h2>
                    <span class="ccx-muted" x-show="!tv">A ward's name opens its dashboard</span>
                    <span class="ccx-pagecount" x-show="tv && tvPage === 'wards' && subPages > 1" x-text="`Page ${subPage + 1} of ${subPages}`"></span>
                </header>
                <div class="ccx-tablewrap" x-ref="wards">
                    <table class="ccx-table" :class="wardColumnsHidden">
                        <thead>
                            <tr>
                                <th scope="col">Ward</th>
                                <th scope="col">Occupancy</th>
                                <th scope="col" title="Available beds">Free</th>
                                <th scope="col" title="Prebooked, waiting for a bed">Prebooked</th>
                                <th scope="col" title="Pending discharge">To discharge</th>
                                <th scope="col" title="Admitted and discharged since midnight">In / out today</th>
                                <th scope="col" title="Patients with an EWS of 5 or more, from vital signs in the last 24 h">EWS&nbsp;5+</th>
                                <th scope="col" title="Patients with monitored assessments or medication doses overdue">Care overdue</th>
                                <th scope="col" title="Inpatients with vital signs in the last 24 h">Vitals 24&nbsp;h</th>
                                <th scope="col" title="Ward alerts waiting for a response">Alerts</th>
                                <th scope="col" title="Nurses on the current shift in the AI Nurse Schedule, against the ward's minimum">Nurses</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="w in s.wards" :key="w.id">
                                <tr data-row :class="{ 'is-changed': changed['ward.' + w.id] }">
                                    <td>
                                        <div class="ccx-ward">
                                            <span class="ccx-ico" :class="'is-' + w.status" x-html="icon(w.status)" role="img" :aria-label="sevLabel(w.status)" :title="sevLabel(w.status)"></span>
                                            <div>
                                                <a :href="w.url" class="name" x-text="w.name"></a><span class="ccx-tag" x-show="w.critical_care" title="Critical care ward">CC</span>
                                                <div class="meta" x-text="[w.code, w.type].filter(Boolean).join(' · ')"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ccx-occ">
                                            <div class="ccx-meter" :class="'is-' + (w.occupancy_status || 'good')" aria-hidden="true">
                                                <span class="fill" :style="`width: ${Math.min(100, w.occupancy || 0)}%`"></span>
                                                <span class="tick" :style="`left: ${s.targets.occupancy}%`"></span>
                                            </div>
                                            <span class="val">
                                                <span x-text="w.occupancy === null ? '—' : dec(w.occupancy) + '%'"></span>
                                                <span class="ccx-sub-num" x-text="`${w.occupied}/${w.beds}`"></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td x-text="fmt(w.free)"></td>
                                    <td><span :class="{ 'ccx-zero': !w.incoming }" x-text="dash(w.incoming)"></span></td>
                                    <td><span :class="{ 'ccx-zero': !w.pending_discharge }" x-text="dash(w.pending_discharge)"></span></td>
                                    <td x-text="`${w.admissions_today} / ${w.discharges_today}`"></td>
                                    <td>
                                        <span class="ccx-flag">
                                            <span class="ccx-ico is-serious" x-show="w.ews_urgent" x-html="icon('serious')"></span>
                                            <span :class="{ 'ccx-zero': !w.ews_urgent }" x-text="dash(w.ews_urgent)"></span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="ccx-flag">
                                            <span class="ccx-ico is-warning" x-show="w.overdue_care" x-html="icon('warning')"></span>
                                            <span :class="{ 'ccx-zero': !w.overdue_care }" x-text="dash(w.overdue_care)"></span>
                                        </span>
                                    </td>
                                    <td>
                                        <span x-text="pct(w.obs_pct)"></span>
                                        <span class="ccx-sub-num" x-show="w.obs_due" x-text="`${w.obs_done} of ${w.obs_due}`"></span>
                                    </td>
                                    <td>
                                        <span class="ccx-flag">
                                            <span class="ccx-ico" :class="w.alerts_waiting_urgent ? 'is-critical' : 'is-warning'" x-show="w.alerts_waiting" x-html="icon(w.alerts_waiting_urgent ? 'critical' : 'warning')"></span>
                                            <span :class="{ 'ccx-zero': !w.alerts }" x-text="dash(w.alerts)"></span>
                                        </span>
                                        <span class="ccx-sub-num" x-show="w.alerts_urgent" x-text="`${w.alerts_urgent} urgent`"></span>
                                    </td>
                                    <td>
                                        <template x-if="w.staffing">
                                            <div>
                                                <span class="ccx-flag">
                                                    <span class="ccx-ico is-serious" x-show="w.staffing.status === 'serious'" x-html="icon('serious')"></span>
                                                    <span x-text="`${w.staffing.on_duty} of ${w.staffing.minimum}`"></span>
                                                </span>
                                                <span class="ccx-sub-num" x-text="w.staffing.shift + (w.staffing.ratio === null ? '' : ` · ${dec(w.staffing.ratio)} per nurse`)"></span>
                                            </div>
                                        </template>
                                        <template x-if="!w.staffing">
                                            <span class="ccx-zero" title="No roster for this ward's current shift">No roster</span>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="s.wards.length === 0">
                                <td colspan="11" style="text-align: center; padding: 2em" class="ccx-zero">No active wards yet</td>
                            </tr>
                        </tbody>
                        <tfoot x-show="s.wards.length > 1">
                            <tr>
                                <td>All wards</td>
                                <td>
                                    <span x-text="s.capacity.occupancy === null ? '—' : dec(s.capacity.occupancy) + '%'"></span>
                                    <span class="ccx-sub-num" x-text="`${s.capacity.occupied}/${s.capacity.beds}`"></span>
                                </td>
                                <td x-text="fmt(s.capacity.free)"></td>
                                <td x-text="fmt(s.capacity.incoming)"></td>
                                <td x-text="fmt(s.capacity.pending_discharge)"></td>
                                <td x-text="`${s.flow.admissions_today} / ${s.flow.discharges_today}`"></td>
                                <td x-text="fmt(s.quality.ews_urgent)"></td>
                                <td x-text="fmt(s.quality.overdue_care)"></td>
                                <td x-text="pct(s.quality.obs_pct)"></td>
                                <td x-text="fmt(s.quality.alerts.pending)"></td>
                                <td x-text="s.workforce.rostered_wards ? `${s.workforce.on_duty} of ${s.workforce.minimum}` : '—'"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </article>

            <article class="ccx-card ccx-attention" aria-labelledby="ccx-attention-title" x-show="cfg.attention.show">
                <header>
                    <h2 id="ccx-attention-title">Needs attention</h2>
                    <span class="ccx-muted" x-text="(s.attention_total ? `${s.attention_total} · most serious first` : '') + (tv && feedPages > 1 ? ` · page ${feedPage + 1} of ${feedPages}` : '')"></span>
                </header>
                <div class="ccx-feedwrap" x-ref="feed" :class="{ 'has-more': feedMore }" @scroll.passive="checkFeed()">
                <ol class="ccx-feed">
                    <template x-for="item in s.attention" :key="item.key">
                        <li class="ccx-item" :class="['is-' + item.severity, { 'is-changed': changed['item.' + item.key] }]">
                            <span class="ccx-ico" x-html="icon(item.severity)"></span>
                            <div>
                                <template x-if="item.url"><a :href="item.url" class="title" x-text="item.title"></a></template>
                                <template x-if="!item.url"><p class="title" x-text="item.title"></p></template>
                                <p class="detail" x-text="item.detail"></p>
                            </div>
                            <span class="sev" x-text="sevLabel(item.severity)"></span>
                        </li>
                    </template>
                </ol>
                <div class="ccx-empty" x-show="s.attention.length === 0">
                    <span class="ccx-ico is-good" x-html="icon('good')"></span>
                    <p><strong>Nothing needs attention</strong></p>
                    <p class="ccx-muted">Every ward is within its thresholds right now.</p>
                </div>
                </div>
                <p class="ccx-more" x-show="feedMore || s.attention_total > s.attention.length"
                    x-text="[feedMore ? 'Scroll for more' : '', s.attention_total > s.attention.length ? `${s.attention_total - s.attention.length} more not listed: see the ward table` : ''].filter(Boolean).join(' · ')"></p>
            </article>
        </section>

        {{-- ------ Trends ------ --}}
        <section class="ccx-section ccx-trends">
            {{-- Midnight census --}}
            <article class="ccx-card ccx-census ccx-page" :class="{ 'is-current': tvPage === 'trends' }" x-show="cfg.trends.show">
                <header>
                    <h2 x-text="`Inpatients at midnight · ${s.trend.census.length} days`"></h2>
                    <button type="button" class="ccx-toggle" @click="tables.census = !tables.census" x-text="tables.census ? 'Chart' : 'Table'" :aria-pressed="tables.census.toString()"></button>
                </header>
                <div class="ccx-chartbody" x-show="tv || !tables.census">
                    <div class="ccx-plotwrap" @mouseleave="hover.census = null">
                        <template x-for="t in census.ticks" :key="'c' + t">
                            <div class="ccx-grid" :class="{ 'is-base': t === 0 }" :style="`bottom: ${t / census.max * 100}%`"><span x-text="fmt(t)"></span></div>
                        </template>
                        <div class="ccx-ref" x-show="s.capacity.beds" :style="`bottom: ${s.capacity.beds / census.max * 100}%`"><span x-text="`${fmt(s.capacity.beds)} beds`"></span></div>
                        <svg class="ccx-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            <path class="area" :d="census.area"></path>
                            <path class="line" :d="census.line" vector-effect="non-scaling-stroke"></path>
                        </svg>
                        <span class="ccx-dot" :style="`left: ${band(s.trend.census.length - 1, s.trend.census.length)}%; bottom: ${s.trend.census[s.trend.census.length - 1] / census.max * 100}%`"></span>
                        <div class="ccx-cross" x-show="hover.census !== null" :style="`left: ${band(hover.census, s.trend.census.length)}%`"></div>
                        <div class="ccx-hits">
                            <template x-for="(v, i) in s.trend.census" :key="'ch' + i">
                                <button type="button" @mouseenter="hover.census = i" @focus="hover.census = i" @blur="hover.census = null"
                                    :aria-label="`${s.trend.days[i]}: ${v} inpatients`"></button>
                            </template>
                        </div>
                        <div class="ccx-tip" x-show="hover.census !== null" x-cloak :style="tipAt(hover.census, s.trend.census.length)">
                            <template x-if="hover.census !== null">
                                <div>
                                    <div class="k" x-text="s.trend.days[hover.census] + (hover.census === s.trend.census.length - 1 ? ' · now' : ' · midnight')"></div>
                                    <div><span class="v" x-text="fmt(s.trend.census[hover.census])"></span> inpatients</div>
                                    <div class="k" x-show="s.capacity.beds" x-text="`${dec(s.trend.census[hover.census] / s.capacity.beds * 100)}% of ${s.capacity.beds} beds`"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="ccx-xaxis"><span x-text="s.trend.labels[0]"></span><span x-text="s.trend.labels[Math.floor(s.trend.labels.length / 2)]"></span><span>Now</span></div>
                </div>
                <div class="ccx-tablescroll" x-show="tables.census && !tv" x-cloak>
                    <table class="ccx-datatable">
                        <thead><tr><th scope="col">Day</th><th scope="col">Inpatients</th><th scope="col">Occupancy</th></tr></thead>
                        <tbody>
                            <template x-for="(v, i) in s.trend.census" :key="'ct' + i">
                                <tr><td x-text="s.trend.days[i]"></td><td x-text="fmt(v)"></td><td x-text="s.capacity.beds ? dec(v / s.capacity.beds * 100) + '%' : '—'"></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </article>

            {{-- Admissions and discharges per day --}}
            <article class="ccx-card ccx-flowchart ccx-page is-later" :class="{ 'is-current': tvPage === 'trends' }" x-show="cfg.trends.show">
                <header>
                    <h2 x-text="`Admissions & discharges · ${s.trend.admissions.length} days`"></h2>
                    <button type="button" class="ccx-toggle" @click="tables.flow = !tables.flow" x-text="tables.flow ? 'Chart' : 'Table'" :aria-pressed="tables.flow.toString()"></button>
                </header>
                <ul class="ccx-legend" style="list-style: none; padding: 0; margin: 0">
                    <li><span class="ccx-key"></span>Admissions</li>
                    <li><span class="ccx-key is-2"></span>Discharges</li>
                </ul>
                <div class="ccx-chartbody" x-show="tv || !tables.flow">
                    <div class="ccx-plotwrap" @mouseleave="hover.flow = null">
                        <template x-for="t in flowScale.ticks" :key="'f' + t">
                            <div class="ccx-grid" :class="{ 'is-base': t === 0 }" :style="`bottom: ${t / flowScale.max * 100}%`"><span x-text="fmt(t)"></span></div>
                        </template>
                        <div class="ccx-cols">
                            <template x-for="(a, i) in s.trend.admissions" :key="'fb' + i">
                                <button type="button" @mouseenter="hover.flow = i" @focus="hover.flow = i" @blur="hover.flow = null"
                                    :aria-label="`${s.trend.days[i]}: ${a} admitted, ${s.trend.discharges[i]} discharged`">
                                    <span class="col">
                                        <span class="cap" x-show="i === s.trend.admissions.length - 1" x-text="a"></span>
                                        <span class="bar" :style="`height: ${a / flowScale.max * 100}%`"></span>
                                    </span>
                                    <span class="col">
                                        <span class="cap" x-show="i === s.trend.admissions.length - 1" x-text="s.trend.discharges[i]"></span>
                                        <span class="bar is-2" :style="`height: ${s.trend.discharges[i] / flowScale.max * 100}%`"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                        <div class="ccx-tip" x-show="hover.flow !== null" x-cloak :style="tipAt(hover.flow, s.trend.admissions.length)">
                            <template x-if="hover.flow !== null">
                                <div>
                                    <div class="k" x-text="s.trend.days[hover.flow] + (hover.flow === s.trend.admissions.length - 1 ? ' · so far' : '')"></div>
                                    <div class="row"><span class="swatch"></span><span class="v" x-text="fmt(s.trend.admissions[hover.flow])"></span><span class="k">admitted</span></div>
                                    <div class="row"><span class="swatch is-2"></span><span class="v" x-text="fmt(s.trend.discharges[hover.flow])"></span><span class="k">discharged</span></div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="ccx-xaxis"><span x-text="s.trend.labels[0]"></span><span x-text="s.trend.labels[Math.floor(s.trend.labels.length / 2)]"></span><span>Today</span></div>
                </div>
                <div class="ccx-tablescroll" x-show="tables.flow && !tv" x-cloak>
                    <table class="ccx-datatable">
                        <thead><tr><th scope="col">Day</th><th scope="col">Admitted</th><th scope="col">Discharged</th></tr></thead>
                        <tbody>
                            <template x-for="(a, i) in s.trend.admissions" :key="'ft' + i">
                                <tr><td x-text="s.trend.days[i]"></td><td x-text="fmt(a)"></td><td x-text="fmt(s.trend.discharges[i])"></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </article>

            {{-- Acuity and risk; in TV mode also each ward's share, as a heat table --}}
            <article class="ccx-card ccx-acuity ccx-page" :class="{ 'is-current': tvPage === 'acuity' }" x-show="cfg.acuity.show">
                <header>
                    <h2>Acuity &amp; risk</h2>
                    <span style="display: inline-flex; flex-wrap: wrap; align-items: center; gap: .4em 1.4em">
                        <span class="ccx-muted" x-text="`Share of ${fmt(s.quality.inpatients)} inpatients`"></span>
                        <span class="ccx-heatkey" aria-hidden="true"><span>Share of the ward</span><span>0%</span><span class="ramp"></span><span>100%</span></span>
                        <span class="ccx-pagecount" x-show="tv && tvPage === 'acuity' && subPages > 1" x-text="`Page ${subPage + 1} of ${subPages}`"></span>
                    </span>
                </header>
                <div class="ccx-hbars">
                    <template x-for="row in s.acuity" :key="row.key">
                        <div class="ccx-hbar">
                            <span class="label" x-text="tv ? row.short : row.label"></span>
                            <span class="value"><span x-text="fmt(row.count)"></span><small x-text="pct(row.pct)"></small></span>
                            <div class="track" aria-hidden="true"><div class="fill" :style="`width: ${acuityMax ? row.count / acuityMax * 100 : 0}%`"></div></div>
                        </div>
                    </template>
                </div>
                <div class="ccx-matrixwrap" x-ref="matrix">
                    <table class="ccx-table ccx-matrix">
                        <thead>
                            <tr>
                                <th scope="col">Ward</th>
                                <th scope="col">Inpatients</th>
                                <template x-for="row in s.acuity" :key="'mh' + row.key">
                                    <th scope="col" x-text="row.short"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="w in s.wards" :key="'m' + w.id">
                                <tr data-row>
                                    <td><span class="name" x-text="w.name"></span><span class="ccx-tag" x-show="w.critical_care">CC</span></td>
                                    <td x-text="fmt(w.inpatients)"></td>
                                    <template x-for="row in s.acuity" :key="'mc' + w.id + row.key">
                                        <td class="heat" :style="heat(w, row.key)"
                                            :title="`${w.name}: ${w[row.key]} of ${w.inpatients} inpatients ${row.short.toLowerCase()}`">
                                            <span :class="{ 'ccx-zero': !w[row.key] }" x-text="dash(w[row.key])"></span>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <p class="ccx-foot">
            Occupancy is occupied beds over active beds, against a target of <span x-text="s.targets.occupancy"></span>% or under.
            Admissions and discharges come from the admission log; the midnight census is worked back from today's inpatients.
            Clinical figures are the ones the ward dashboards raise; an EWS counts while its vital signs are under <span x-text="s.targets.obs_hours"></span> h old.
            Alerts count as waiting after <span x-text="s.targets.alert_wait_minutes"></span> min, and response times only include alerts a person answered.
            Nurses are those rostered for each ward's current shift in the AI Nurse Schedule. No patient is named on this screen.
        </p>
        </div>

        @include('command-center-v2.partials.settings')
    </div>

    <script type="application/json" id="ccv2-snapshot">@json($snapshot)</script>

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
                { key: 'wards', label: 'Wards' },
                { key: 'trends', label: 'Trends' },
                { key: 'acuity', label: 'Acuity & risk' },
            ];
            // How long each page (or each screenful of a page's rows) stays up, and each page of the attention list
            const STEP_SECONDS = 15;
            const FEED_SECONDS = 10;

            // ------ Display settings (partials/settings), kept in this browser so each screen has its own ------
            const SETTINGS_KEY = 'ccv2Display';
            // Screen presets size the board for a screen of that resolution whatever the computer's display scaling:
            // type TEXT_RATIO of the screen's height, a little larger than Auto's so it reads from across a room
            const TEXT_RATIO = 0.016;
            const SCREENS = [
                { key: 'auto', label: 'Auto', sub: 'Fits the window' },
                { key: 'hd', label: '720p', sub: 'HD · 1280 × 720', height: 720 },
                { key: 'fhd', label: '1080p', sub: 'Full HD · 1920 × 1080', height: 1080 },
                { key: 'qhd', label: '2K', sub: 'QHD · 2560 × 1440', height: 1440 },
                { key: 'uhd', label: '4K', sub: 'UHD · 3840 × 2160', height: 2160 },
            ];
            // The whole board's size and each section's, as percentages
            const SIZE = { min: 50, max: 250 };
            const SECTION_SIZE = { min: 50, max: 200 };
            const ROWS = [3, 4, 5, 6, 7, 8, 9, 10, 12, 14, 16, 18, 20, 25, 30];
            const SECTIONS = [
                { key: 'kpis', label: 'Headline figures', about: 'The four cards across the top' },
                { key: 'wards', label: 'Wards', about: 'The ward table', perPage: 'Wards per page', pageSizes: ROWS, seconds: true },
                { key: 'trends', label: 'Trends', about: 'Inpatients at midnight, admissions and discharges', seconds: true },
                { key: 'acuity', label: 'Acuity & risk', about: 'In TV mode, with each ward\'s share', perPage: 'Wards per page', pageSizes: ROWS, seconds: true },
                { key: 'attention', label: 'Needs attention', about: 'The list beside the pages', perPage: 'Items per page', pageSizes: [2, 3, 4, 5, 6, 8, 10, 12], seconds: true },
            ];
            const SECONDS = [5, 8, 10, 15, 20, 30, 45, 60, 90, 120];
            // The ward table's columns that can be left out, with n their place in the row (the CSS hides them by
            // it). The ward's own column always shows
            const WARD_COLUMNS = [
                { key: 'occupancy', n: 2, label: 'Occupancy' }, { key: 'free', n: 3, label: 'Free' },
                { key: 'prebooked', n: 4, label: 'Prebooked' }, { key: 'to_discharge', n: 5, label: 'To discharge' },
                { key: 'in_out', n: 6, label: 'In / out today' }, { key: 'ews', n: 7, label: 'EWS 5+' },
                { key: 'care_overdue', n: 8, label: 'Care overdue' }, { key: 'vitals', n: 9, label: 'Vitals 24 h' },
                { key: 'alerts', n: 10, label: 'Alerts' }, { key: 'nurses', n: 11, label: 'Nurses' },
            ];
            const defaults = () => ({
                screen: 'auto',
                size: 100,
                kpis: { show: true, size: 100 },
                wards: { show: true, size: 100, perPage: 0, seconds: STEP_SECONDS, hidden: [] },
                trends: { show: true, size: 100, seconds: STEP_SECONDS },
                acuity: { show: true, size: 100, perPage: 0, seconds: STEP_SECONDS },
                attention: { show: true, size: 100, perPage: 0, seconds: FEED_SECONDS },
            });
            // Saved settings, taken field by field where they are one of the panel's choices; the rest are the defaults
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
                    if (to.hidden && Array.isArray(from.hidden)) to.hidden = WARD_COLUMNS.map((column) => column.key).filter((key) => from.hidden.includes(key));
                });
                if (!TV_PAGES.some((page) => cfg[page.key].show)) cfg.wards.show = true;
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

            window.Alpine.data('commandCenterV2', () => ({
                s: JSON.parse(document.getElementById('ccv2-snapshot').textContent),
                url: null,
                connection: 'live',
                loading: false,
                lastOk: Date.now(),
                now: new Date(),
                cycle: 0,
                timer: null,
                theme: 'dark',
                tv: false,
                changed: {},
                hover: { census: null, flow: null },
                tables: { census: false, flow: false },
                feedMore: false,
                // This screen's display settings (partials/settings), and what they allow at the current sizes: how
                // many rows fit a TV page, and how far the ward table had to shrink to fit its card
                cfg: defaults(),
                settingsOpen: false,
                dpr: 1,
                screenPx: { width: 0, height: 0 },
                screens: SCREENS,
                sections: SECTIONS,
                secondsOptions: SECONDS,
                wardColumns: WARD_COLUMNS,
                fits: { wards: 0, acuity: 0 },
                wardsFit: 1,
                // TV mode's pages: the one up, and which screenful of its rows. They turn until someone presses Pause
                paused: false,
                stepSeconds: STEP_SECONDS,
                tvPage: 'wards',
                subPage: 0,
                subPages: 1,
                feedPage: 0,
                feedPages: 1,
                stepLeft: STEP_SECONDS,
                stepKey: 0,
                feedTick: 0,

                init() {
                    this.url = this.$el.dataset.url;
                    try { this.theme = localStorage.getItem('ccv2Theme') === 'light' ? 'light' : 'dark'; } catch (e) {}
                    try { this.tv = localStorage.getItem('ccv2Tv') === 'on'; } catch (e) {}
                    try { this.cfg = readSettings(JSON.parse(localStorage.getItem(SETTINGS_KEY))); } catch (e) {}
                    this.$watch('cfg', () => this.settingsChanged());
                    // The display scaling or zoom changing moves a screen preset's size in CSS pixels
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
                        [this.$refs.feed, this.$refs.wards, this.$refs.matrix].forEach((box) => resized.observe(box));
                    }

                    // Straight back up to date when the screen is looked at again
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible' && Date.now() - this.lastOk >= this.s.refresh_seconds * 1000) this.load();
                    });
                    // Leaving the browser's full screen (Esc) leaves TV mode too
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
                    // Nobody is looking: wait for the next round rather than ask the server
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
                        const next = await response.json();
                        this.markChanges(this.s, next);
                        this.s = next;
                        this.connection = 'live';
                        this.lastOk = Date.now();
                        this.$nextTick(() => this.refit());
                    } catch (e) {
                        this.connection = 'retrying';
                    } finally {
                        this.loading = false;
                        if (this.connection !== 'signed-out') this.schedule();
                    }
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
                        capacity: (s) => s.capacity,
                        flow: (s) => s.flow,
                        quality: (s) => ({ ...s.quality, alerts: { ...s.quality.alerts, oldest: null, median_week: null } }),
                        workforce: (s) => s.workforce,
                    };
                    for (const [key, pick] of Object.entries(cards)) {
                        if (sign(pick(before)) !== sign(pick(after))) flash(key);
                    }
                    const ward = (w) => sign({ ...w, alerts_oldest: null });
                    const was = Object.fromEntries(before.wards.map((w) => [w.id, ward(w)]));
                    after.wards.forEach((w) => { if (w.id in was && was[w.id] !== ward(w)) flash('ward.' + w.id); });
                    const listed = new Set(before.attention.map((item) => item.key));
                    after.attention.forEach((item) => { if (!listed.has(item.key)) flash('item.' + item.key); });
                },

                toggleTheme() {
                    this.theme = this.theme === 'dark' ? 'light' : 'dark';
                    try { localStorage.setItem('ccv2Theme', this.theme); } catch (e) {}
                },

                setTv(on) {
                    this.tv = on;
                    try { localStorage.setItem('ccv2Tv', on ? 'on' : 'off'); } catch (e) {}
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
                    // Once the layout has settled at its new size (the menu takes 200 ms to go)
                    setTimeout(() => {
                        [this.$refs.wards, this.$refs.matrix, this.$refs.feed].forEach((box) => { box.scrollTop = 0; });
                        this.refit();
                    }, 300);
                },

                // Whether the attention list runs on past what its card shows; TV mode pages through it instead
                checkFeed() {
                    const feed = this.$refs.feed;
                    this.feedMore = !this.tv && !!feed && feed.scrollHeight - feed.scrollTop - feed.clientHeight > 4;
                },

                // After new figures or a new size: the pages as they now fall
                refit() {
                    this.checkFeed();
                    if (this.tv) {
                        this.fitWards();
                        this.goSubPage(this.subPage);
                        this.goFeedPage(this.feedPage);
                    }
                },

                // TV mode, every second. The wards, trends and acuity take turns, a page whose rows do not all
                // fit showing them a screenful at a time; the attention list pages on its own. Pause holds it all
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

                // One screenful of the rows on the page up: the ward table's, or the acuity heat table's. As many
                // as fit, or the number a page set in the display settings
                goSubPage(page) {
                    const key = this.tvPage;
                    const box = { wards: this.$refs.wards, acuity: this.$refs.matrix }[key];
                    if (!box || box.offsetParent === null) {
                        this.subPage = 0;
                        this.subPages = 1;
                        return;
                    }
                    const rows = [...box.querySelectorAll('tbody tr[data-row]')];
                    const foot = box.querySelector('tfoot');
                    const bottom = foot && foot.offsetParent !== null ? foot.offsetHeight : 0;
                    const pages = this.pages(box, rows, bottom, this.shareRows(key, box, rows, bottom));
                    this.subPages = pages.length;
                    this.subPage = Math.min(page, pages.length - 1);
                    this.turnTo(box, rows, pages[this.subPage]);
                },
                // How many of a table's rows fit its card at their own height (for the settings panel). The rows
                // on a page then grow to share the card's height between them, as many as fit or fewer if set,
                // rather than leave a gap under the last. Gives the number a page to keep to, 0 for as many as fit
                shareRows(key, box, rows, bottom) {
                    const limit = this.cfg[key].perPage;
                    box.style.removeProperty('--ccx-row');
                    if (!rows.length) return limit;
                    const origin = box.getBoundingClientRect().top - box.scrollTop;
                    const view = box.clientHeight - (rows[0].getBoundingClientRect().top - origin) - bottom;
                    // The first page's rows at their own height, or where they all fit, as many of their average height
                    const natural = this.pages(box, rows, bottom);
                    const height = rows.reduce((sum, row) => sum + row.getBoundingClientRect().height, 0) / rows.length;
                    this.fits[key] = natural.length > 1 ? natural[0].rows.length : Math.max(rows.length, Math.floor((view + 1) / height));
                    box.style.setProperty('--ccx-row', Math.floor(view / Math.min(limit || Infinity, this.fits[key])) + 'px');
                    return limit;
                },
                // TV mode: a ward table wider than its card, at the sizes and with the columns chosen, is scaled
                // down until every column fits rather than have the last ones cut off
                fitWards() {
                    const box = this.$refs.wards;
                    box.style.removeProperty('--ccx-fit');
                    this.wardsFit = 1;
                    if (box.offsetParent === null) return;
                    const over = box.querySelector('table').offsetWidth / box.clientWidth;
                    if (over > 1.005) {
                        this.wardsFit = Math.floor(100 / over) / 100;
                        box.style.setProperty('--ccx-fit', this.wardsFit);
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

                // The pages a box's rows fall into: each takes the rows that fit whole between where the first
                // row sits (under any sticky header) and the sticky footer (bottom), up to limit rows if set, and
                // its scroll offset puts its first row where the first row of all sits
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
                // Show one page: its rows fade in where the last page was, and the rest are hidden, so
                // nothing half-cut peeks in at the bottom
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
                // Closed by Done, a click beside the panel, or Esc (the dialog's close event, which can come late):
                // the pages turn again from the one up
                settingsClosed() {
                    if (!this.settingsOpen) return;
                    this.settingsOpen = false;
                    this.restartStep();
                },
                // A setting changed: keep it for this screen, and lay the pages out again at the new sizes
                settingsChanged() {
                    try { localStorage.setItem(SETTINGS_KEY, JSON.stringify(this.cfg)); } catch (e) {}
                    if (!this.tvPages.some((page) => page.key === this.tvPage)) this.showTv(this.tvPages[0].key);
                    this.$nextTick(() => this.refit());
                },
                // In TV mode, working on a page's settings brings that page up, so each change shows as it is made
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
                // TV mode keeps at least one of its pages to show
                canHide(key) {
                    return !TV_PAGES.some((page) => page.key === key) || this.tvPages.length > 1;
                },
                toggleSection(key) {
                    const section = this.cfg[key];
                    if (!section.show || this.canHide(key)) section.show = !section.show;
                },
                toggleColumn(key) {
                    const { hidden } = this.cfg.wards;
                    this.cfg.wards.hidden = hidden.includes(key) ? hidden.filter((other) => other !== key) : [...hidden, key];
                },

                get tvPages() {
                    return TV_PAGES.filter((page) => this.cfg[page.key].show);
                },
                // The board's size: the screen preset's type size in CSS pixels (a preset's pixels are the screen's
                // own, which the display scaling multiplies), times the size chosen; then each section's own size
                get boardStyle() {
                    const { cfg } = this;
                    const preset = SCREENS.find((screen) => screen.key === cfg.screen);
                    const vars = [`--ccx-size: ${cfg.size / 100}`, ...SECTIONS.map((section) => `--ccx-${section.key}: ${cfg[section.key].size / 100}`)];
                    if (preset && preset.height) vars.push(`--ccx-base: ${(preset.height * TEXT_RATIO / this.dpr).toFixed(2)}px`);
                    return vars.join('; ');
                },
                get wardColumnsHidden() {
                    return WARD_COLUMNS.filter((column) => this.cfg.wards.hidden.includes(column.key)).map((column) => 'hide-' + column.n);
                },
                // The screen in its own pixels, with its scaling. Some browsers say less than the window they
                // fill (or nothing), so never less than the window
                measureScreen() {
                    this.dpr = window.devicePixelRatio || 1;
                    this.screenPx = {
                        width: Math.round(Math.max(window.screen.width || 0, window.innerWidth) * this.dpr),
                        height: Math.round(Math.max(window.screen.height || 0, window.innerHeight) * this.dpr),
                    };
                },
                // The screen, and the preset nearest it
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
                // Under a page's number a page: how many fit at the current size, from TV mode's last layout
                fitNote(key) {
                    if (!this.tv) return 'Takes effect in TV mode';
                    const fit = this.fits[key];
                    if (!fit) return '';
                    return this.cfg[key].perPage > fit ? `Only ${fit} fit at this size` : `${fit} fit at this size`;
                },
                get wardsFitNote() {
                    return this.tv && this.wardsFit < 1
                        ? `Shrunk to ${Math.round(this.wardsFit * 100)}% so every column fits. Leave some out to make it bigger.`
                        : '';
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

                // Average stay: shorter than the 30 days before is the better way
                get alosChange() {
                    const { alos, alos_previous: before } = this.s.flow;
                    if (missing(alos) || missing(before)) return '';
                    const d = Math.round((alos - before) * 10) / 10;
                    return d === 0 ? 'same as before' : `${d > 0 ? '▲' : '▼'} ${this.dec(Math.abs(d))} d vs previous`;
                },
                get alosTone() {
                    const { alos, alos_previous: before } = this.s.flow;
                    if (missing(alos) || missing(before) || Math.round((alos - before) * 10) === 0) return '';
                    return alos < before ? 'is-down-good' : 'is-up-bad';
                },

                get census() {
                    const values = this.s.trend.census;
                    const n = values.length;
                    const { max, ticks } = scale(Math.max(this.s.capacity.beds || 0, ...values));
                    const points = values.map((v, i) => [this.band(i, n), 100 - v / max * 100]);
                    const line = points.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(2) + ' ' + p[1].toFixed(2)).join(' ');
                    const area = n ? `${line} L${points[n - 1][0].toFixed(2)} 100 L${points[0][0].toFixed(2)} 100 Z` : '';
                    return { max, ticks, line, area };
                },
                get flowScale() {
                    return scale(Math.max(0, ...this.s.trend.admissions, ...this.s.trend.discharges));
                },
                get acuityMax() {
                    return Math.max(0, ...this.s.acuity.map((row) => row.count));
                },

                // Centre of band i of n, as a percentage across the plot
                band(i, n) {
                    return (i + 0.5) / n * 100;
                },
                tipAt(i, n) {
                    if (i === null) return '';
                    const x = this.band(i, n);
                    return `left: ${x}%; transform: translateX(${x < 30 ? '-8%' : x > 70 ? '-92%' : '-50%'})`;
                },

                icon(severity) {
                    return ICONS[severity] || ICONS.good;
                },
                sevLabel(severity) {
                    return { critical: 'Critical', serious: 'Serious', warning: 'Watch', good: 'OK' }[severity] || '';
                },
                capacityLabel(status) {
                    return { good: 'Within target', warning: 'Above target', critical: 'Near full' }[status] || '';
                },
                qualityLabel(status) {
                    return { good: 'No exceptions', warning: 'Watch', serious: 'Serious', critical: 'Critical' }[status] || '';
                },
                workforceLabel(status) {
                    return { good: 'Staffed to minimum', serious: 'Below minimum' }[status] || '';
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
                // A count in the ward table: a dash for none, so the ones with some stand out
                dash(n) {
                    return n ? this.fmt(n) : '–';
                },
                // A heat-table cell: one hue, deeper the bigger the share of the ward's inpatients
                heat(ward, key) {
                    const count = ward[key] || 0;
                    if (!count || !ward.inpatients) return '';
                    return `background: rgb(var(--heat) / ${(0.16 + 0.64 * Math.min(1, count / ward.inpatients)).toFixed(2)})`;
                },
                mins(n) {
                    return missing(n) ? DASH : (n < 10 ? this.dec(n) : Math.round(n).toLocaleString('en-US')) + ' min';
                },
                days(n) {
                    return missing(n) ? DASH : this.dec(n) + (n === 1 ? ' day' : ' days');
                },
                // 12 -> "12 pm", 11 -> "11 am"
                hour(h) {
                    return `${h % 12 || 12} ${h < 12 ? 'am' : 'pm'}`;
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
