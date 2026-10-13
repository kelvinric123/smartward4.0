{{--
    Share button and dialog for the AI Nurse Schedule, in the ward and week bar,
    inside the page's Alpine scope so it knows the tab being looked at. Two ways
    to share: a link to this page for staff who sign in, and a view-only link
    that needs no sign-in (AiNurseScheduleShareController), which only nurse
    managers and admins can make or revoke. The ward's links that still work
    are listed with their views, a copy button and a QR code.
--}}
@php
    $shareModel = \App\Models\WardRosterShareLink::class;
    $shareCreated = session('share_created') ? (int) session('share_created') : null;
    // A create form that failed validation opens again with what was chosen
    $shareFailed = collect($errors->keys())->contains(fn (string $key) => \Illuminate\Support\Str::is(['label', 'sections', 'sections.*', 'week_mode', 'expires_in', 'names'], $key));
    $shareConfig = [
        'open' => $shareFailed || $shareCreated || session('share_open'),
        'highlight' => $shareCreated,
        'pageUrl' => route('ward.ai-schedule', ['ward_id' => $ward->id, 'week' => $weekStart->toDateString()]),
        'sections' => $shareFailed ? array_values(array_filter((array) old('sections', []), 'is_string')) : ['roster'],
        'keepSections' => $shareFailed,
        // What a new link shows by default: the roster, and the part of the schedule being looked at
        'tabSections' => ['roster' => 'roster', 'acuity' => 'acuity', 'assign' => 'assign', 'workload' => 'workload', 'leave' => 'roster'],
    ];
    $shareControl = 'rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $shareHeading = 'text-sm font-semibold text-gray-900';
    $shareLabel = 'mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500';
    $shareSecondary = 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50';
@endphp

@vite('resources/js/qrcode.js')

<script>
    window.aiScheduleShare = function (config) {
        return {
            open: config.open,
            highlight: config.highlight,
            sections: config.sections,
            keepSections: config.keepSections,
            copied: null,
            qr: null,
            qrReady: false,

            init() {
                // The QR library loads after Alpine starts; QR codes show once it is there
                const ready = () => { this.qrReady = !!window.QRCode; };
                document.readyState === 'complete' ? ready() : window.addEventListener('load', ready);
                if (this.open && this.highlight) {
                    this.$nextTick(() => {
                        const item = document.getElementById('share-link-' + this.highlight);
                        if (!item) return;
                        item.scrollIntoView({ block: 'nearest' });
                        const input = item.querySelector('input');
                        if (input) input.select();
                    });
                }
            },
            // Opens with the roster and the part of the schedule in view ticked for a new link
            show(tab) {
                if (!this.keepSections) {
                    const section = config.tabSections[tab] || 'roster';
                    this.sections = section === 'roster' ? ['roster'] : ['roster', section];
                }
                this.qrReady = !!window.QRCode;
                this.open = true;
                this.$nextTick(() => this.$refs.sharePanel && this.$refs.sharePanel.focus());
            },
            pageUrl(tab) {
                return config.pageUrl + '&tab=' + encodeURIComponent(tab);
            },
            copy(input, key) {
                if (!input) return;
                input.focus();
                input.select();
                const done = () => {
                    this.copied = key;
                    setTimeout(() => { if (this.copied === key) this.copied = null; }, 2000);
                };
                // The clipboard API needs https or localhost; on a plain-http hospital network copy the selection
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(input.value).then(done, () => document.execCommand('copy') && done());
                } else if (document.execCommand('copy')) {
                    done();
                }
            },
            toggleQr(id, url) {
                this.qr = this.qr === id ? null : id;
                if (this.qr === null || !window.QRCode) return;
                this.$nextTick(() => {
                    const box = document.querySelector('[data-share-qr="' + id + '"]');
                    if (!box || box.dataset.drawn) return;
                    window.QRCode.toString(url, { type: 'svg', margin: 1, width: 160, errorCorrectionLevel: 'M' })
                        .then((svg) => {
                            box.innerHTML = svg;
                            box.dataset.drawn = '1';
                        })
                        .catch(() => {
                            this.qr = null;
                            this.qrReady = false;
                        });
                });
            },
        };
    };
</script>

<div x-data="aiScheduleShare(@js($shareConfig))" @keydown.escape.window="open = false">
    <button type="button" @click="show(tab)" title="Share the schedule with other staff"
        class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
        </svg>
        Share
        @if ($shareLinks->isNotEmpty())
            <span class="ml-1.5 inline-flex items-center justify-center min-w-[1.25rem] px-1.5 rounded-full bg-indigo-100 text-[11px] font-bold text-indigo-700"
                title="{{ $shareLinks->count() }} view-only {{ \Illuminate\Support\Str::plural('link', $shareLinks->count()) }} open">{{ $shareLinks->count() }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-3"
        role="dialog" aria-modal="true" aria-labelledby="ai-share-title">
        <div class="absolute inset-0 bg-gray-900/50" @click="open = false"></div>

        <div x-ref="sharePanel" tabindex="-1"
            class="relative flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl focus:outline-none">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
                <div>
                    <h3 id="ai-share-title" class="text-base font-semibold text-gray-900">Share the AI Nurse Schedule</h3>
                    <p class="text-xs text-gray-500">{{ $ward->ward_name }} &middot; week of {{ $weekStart->format('j M Y') }}</p>
                </div>
                <button type="button" @click="open = false" class="p-1 text-gray-400 hover:text-gray-600" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 divide-y divide-gray-200 overflow-y-auto">
                {{-- 1. A link to this page, for staff with a SmartWard sign-in --}}
                <section class="px-5 py-4">
                    <h4 class="{{ $shareHeading }}">Staff who sign in to SmartWard</h4>
                    <p class="mt-0.5 text-xs text-gray-500">A link to this page on this ward, week and tab. They see what their own account allows once signed in.</p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input type="text" readonly x-ref="pageLink" :value="pageUrl(tab)" @focus="$event.target.select()" aria-label="Link to this page"
                            class="{{ $shareControl }} min-w-0 flex-1 bg-gray-50 font-mono text-xs">
                        <button type="button" @click="copy($refs.pageLink, 'page')" class="{{ $shareSecondary }}">
                            <span x-text="copied === 'page' ? 'Copied' : 'Copy'">Copy</span>
                        </button>
                    </div>
                </section>

                {{-- 2. A view-only link that needs no sign-in --}}
                <section class="px-5 py-4">
                    <h4 class="{{ $shareHeading }}">Anyone with the link: view only, no sign-in</h4>
                    <p class="mt-0.5 text-xs text-gray-500">
                        For nurses who use only the nurse app, a staff-room screen, or the ward's staff chat. The page refreshes itself
                        and stops working when the link expires or is revoked.
                    </p>

                    @if ($canEdit)
                        <form method="POST" action="{{ route('ward.ai-schedule.shares.store') }}" class="mt-3 space-y-4">
                            @csrf
                            <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                            <input type="hidden" name="tab" :value="tab">

                            <div>
                                <label for="ai_share_label" class="{{ $shareLabel }}">Name <span class="normal-case tracking-normal font-normal">(optional)</span></label>
                                <input type="text" id="ai_share_label" name="label" maxlength="100" value="{{ old('label') }}"
                                    placeholder="e.g. {{ $ward->ward_name }} staff chat"
                                    class="{{ $shareControl }} w-full {{ $errors->has('label') ? 'border-red-400' : '' }}">
                                @error('label')
                                    <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <fieldset>
                                    <legend class="{{ $shareLabel }}">What to show</legend>
                                    <div class="space-y-0.5">
                                        @foreach ($shareModel::SECTIONS as $key => $section)
                                            <label class="flex cursor-pointer items-start gap-2 rounded px-1 py-1 text-sm text-gray-700 hover:bg-gray-50">
                                                <input type="checkbox" name="sections[]" value="{{ $key }}" x-model="sections"
                                                    class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                <span>
                                                    {{ $section['label'] }}
                                                    <span class="block text-xs text-gray-500">{{ $section['description'] }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @if ($errors->has('sections') || $errors->has('sections.*'))
                                        <p class="mt-0.5 text-xs text-red-600">{{ $errors->first('sections') ?: $errors->first('sections.*') }}</p>
                                    @endif
                                </fieldset>

                                <div class="space-y-4">
                                    <fieldset>
                                        <legend class="{{ $shareLabel }}">Week</legend>
                                        <div class="space-y-1 text-sm text-gray-700">
                                            <label class="flex items-start gap-2">
                                                <input type="radio" name="week_mode" value="rolling" @checked(old('week_mode', 'rolling') === 'rolling')
                                                    class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                                                <span>Always the current week <span class="block text-xs text-gray-500">Viewers can also open next week</span></span>
                                            </label>
                                            <label class="flex items-start gap-2">
                                                <input type="radio" name="week_mode" value="fixed" @checked(old('week_mode') === 'fixed')
                                                    class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                                                <span>This week only <span class="block text-xs text-gray-500">{{ $weekStart->format('D j M') }} &ndash; {{ $weekStart->copy()->addDays(6)->format('D j M Y') }}</span></span>
                                            </label>
                                        </div>
                                    </fieldset>

                                    <div>
                                        <label for="ai_share_expires" class="{{ $shareLabel }}">Expires after</label>
                                        <select id="ai_share_expires" name="expires_in" class="{{ $shareControl }} w-full">
                                            @foreach ($shareModel::EXPIRY_DAYS as $days)
                                                <option value="{{ $days }}" @selected((int) old('expires_in', 7) === $days)>{{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <fieldset>
                                        <legend class="{{ $shareLabel }}">Patient names</legend>
                                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-700">
                                            @foreach ($shareModel::NAME_MODES as $mode => $label)
                                                <label class="flex items-center gap-2">
                                                    <input type="radio" name="names" value="{{ $mode }}" @checked(old('names', 'hidden') === $mode)
                                                        class="text-indigo-600 focus:ring-indigo-500">
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                        <p class="mt-0.5 text-xs text-gray-500">On the bed assignment and patient acuity. Never full names or MRNs.</p>
                                        @error('names')
                                            <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </fieldset>
                                </div>
                            </div>

                            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                Anyone who has the link can open it, without signing in, until it expires or is revoked. It never shows
                                full patient names, MRNs, the care needs behind a level, leave types, notes or requests. Share it in staff
                                channels only.
                            </p>

                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <span x-show="!sections.length" class="text-xs text-red-600">Tick at least one part to show.</span>
                                <button type="submit" :disabled="!sections.length"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    Create link
                                </button>
                            </div>
                        </form>
                    @else
                        <p class="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600">
                            A nurse manager or admin can create a view-only link for staff who do not sign in to SmartWard.
                        </p>
                    @endif
                </section>

                {{-- 3. The ward's links that still work --}}
                <section class="px-5 py-4">
                    <h4 class="{{ $shareHeading }}">View-only links open now ({{ $shareLinks->count() }})</h4>

                    @forelse ($shareLinks as $link)
                        @php
                            $url = $link->url();
                            $views = $link->view_count
                                ? $link->view_count . ' ' . \Illuminate\Support\Str::plural('view', $link->view_count) . ', last ' . $link->last_viewed_at?->diffForHumans()
                                : 'Not opened yet';
                        @endphp
                        <div id="share-link-{{ $link->id }}" class="mt-2 rounded-lg border p-3"
                            :class="highlight === {{ $link->id }} ? 'border-indigo-400 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200'">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900">
                                        {{ $link->title() }}
                                        <span x-show="highlight === {{ $link->id }}" x-cloak class="inline-flex items-center rounded-full bg-indigo-600 px-2 py-0.5 text-[11px] font-bold text-white">New</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($link->sectionKeys() as $key)
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700">{{ $shareModel::SECTIONS[$key]['label'] }}</span>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs text-gray-600">
                                        {{ $link->isRolling() ? 'Current week (and next)' : 'Week of ' . $link->week_start->format('j M Y') }}
                                        &middot; Patients: {{ $shareModel::NAME_MODES[$link->names] ?? 'Bed only' }}
                                        &middot; Expires {{ $link->expires_at->format('D j M Y, H:i') }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ $views }}
                                        @if ($link->created_by_name)
                                            &middot; made by {{ $link->created_by_name }} {{ $link->created_at?->format('j M H:i') }}
                                        @endif
                                    </p>
                                </div>
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('ward.ai-schedule.shares.revoke', $link) }}"
                                        onsubmit="return confirm(@js('Revoke "' . $link->title() . '"? Anyone who opens it is then told it has expired.'))">
                                        @csrf
                                        <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                                        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                                        <input type="hidden" name="tab" :value="tab">
                                        <button type="submit" class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                            Revoke
                                        </button>
                                    </form>
                                @endif
                            </div>

                            @if ($url)
                                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                                    <input type="text" readonly value="{{ $url }}" x-ref="shareLink{{ $link->id }}" @focus="$event.target.select()" aria-label="View-only link"
                                        class="{{ $shareControl }} min-w-0 flex-1 bg-gray-50 font-mono text-xs">
                                    <div class="flex gap-2">
                                        <button type="button" @click="copy($refs.shareLink{{ $link->id }}, {{ $link->id }})" class="{{ $shareSecondary }}">
                                            <span x-text="copied === {{ $link->id }} ? 'Copied' : 'Copy'">Copy</span>
                                        </button>
                                        <button type="button" x-show="qrReady" x-cloak @click="toggleQr({{ $link->id }}, @js($url))" class="{{ $shareSecondary }}"
                                            :aria-expanded="qr === {{ $link->id }}">
                                            QR code
                                        </button>
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="{{ $shareSecondary }}" title="Open the view-only page in a new tab">Open</a>
                                    </div>
                                </div>
                                <div x-show="qrReady && qr === {{ $link->id }}" x-cloak class="mt-3 flex flex-col items-center gap-3 sm:flex-row">
                                    <div data-share-qr="{{ $link->id }}" class="h-44 w-44 shrink-0 rounded-lg border border-gray-200 bg-white p-2"></div>
                                    <p class="text-xs text-gray-500">Scan it with a phone camera to open the link: quicker than typing it on a ward phone or the staff-room screen.</p>
                                </div>
                            @else
                                <p class="mt-2 text-xs text-amber-800">
                                    This link still works, but SmartWard can no longer show it: its app key has changed since the link was made.
                                    Revoke it and create a new one to share again.
                                </p>
                            @endif
                        </div>
                    @empty
                        <p class="mt-1 text-sm text-gray-500">No view-only links for {{ $ward->ward_name }} yet.</p>
                    @endforelse
                </section>
            </div>

            <div class="flex justify-end border-t border-gray-200 bg-gray-50 px-5 py-3">
                <button type="button" @click="open = false"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
