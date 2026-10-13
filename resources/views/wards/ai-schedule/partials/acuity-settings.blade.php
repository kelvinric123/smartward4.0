{{--
    Acuity staffing settings on the Patient acuity tab: the hours of nursing
    care a patient at each care level needs in 24 h, the share of the day's
    care on each shift, and the early warning score system the indicators use.
    Everyone can see them; only editors can change them.
    Lives inside the staffing card's x-data, which holds settingsOpen.
--}}

@php
    $s = \App\Services\NurseScheduling\AcuitySettings::class;
    $fmt = fn ($value) => $s::format((float) $value);
    $ewsValue = old('acuity.ews_system', $acuitySettings->ewsSystem());
    $ewsChanged = $acuitySettings->ewsSystem() !== $s::DEFAULT_EWS_SYSTEM;
    // The shares as typed, so the total can be checked before saving
    $shareValues = collect(\App\Services\NurseScheduling\WardShifts::CODES)
        ->mapWithKeys(fn (string $code) => [$code => (float) old('acuity.share_' . $code, $acuitySettings->share($code))])
        ->all();

    // Worked example: ten Level 2 patients on the AM shift
    $exampleHours = 10 * $acuitySettings->hours(2);
    $exampleShift = $exampleHours * $acuitySettings->share('AM') / 100;
    $exampleLength = (float) ($shifts['AM']['hours'] ?? 0);
    $exampleExact = $exampleLength > 0 ? $exampleShift / $exampleLength : 0;
    $exampleNurses = max(1, (int) ceil(round($exampleExact, 2) - \App\Services\NurseScheduling\AcuityStaffing::ROUNDING_SLACK));
@endphp

<div x-show="settingsOpen" x-cloak class="px-5 pt-5">
    <div id="acuity-settings" class="rounded-xl border border-gray-200 bg-gray-50/70 scroll-mt-4"
        x-data="{
            shares: @js($shareValues),
            get shareTotal() { return Object.values(this.shares).reduce((sum, value) => sum + (Number(value) || 0), 0); },
            get sharesOk() { return Math.abs(this.shareTotal - 100) <= {{ $s::SHARE_TOLERANCE }}; },
        }">
        <form method="POST" action="{{ route('ward.ai-schedule.acuity.settings') }}">
            @csrf
            <input type="hidden" name="ward_id" value="{{ $ward->id }}">
            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">

            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-4 py-3">
                <div>
                    <h4 class="text-sm font-bold text-gray-800">Acuity staffing for {{ $ward->ward_name }}</h4>
                    <p class="mt-0.5 max-w-3xl text-xs text-gray-500">
                        The hours of nursing care a patient at each level needs in 24 h, how the day's care is spread over the
                        shifts, and the early warning score the indicators use. The defaults are starting values only: set them
                        from your ward's own care and review them over time.
                    </p>
                </div>
                @if ($acuitySettings->changedCount() > 0)
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                        {{ $acuitySettings->changedCount() }} changed from the defaults
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
                @foreach ($s::GROUPS as $group => $fields)
                    <fieldset class="rounded-lg border border-gray-200 bg-white p-3">
                        <legend class="px-1 text-xs font-bold uppercase tracking-wider text-gray-600">{{ $group }}</legend>
                        <div class="space-y-2.5">
                            @foreach ($fields as $key => $field)
                                @php
                                    $percent = ($field['unit'] ?? null) === '%';
                                    $code = $percent ? substr($key, strlen('share_')) : null;
                                    $changed = !$acuitySettings->isDefault($key);
                                @endphp
                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <label for="acuity_{{ $key }}" class="min-w-0">
                                            <span class="block text-sm font-medium text-gray-800">{{ $field['label'] }}</span>
                                            <span class="block text-[11px] leading-snug text-gray-500">
                                                {{ $field['hint'] }} &middot; default {{ $fmt($s::DEFAULTS[$key]) }}{{ $percent ? '%' : ' h' }}
                                            </span>
                                        </label>
                                        <div class="flex shrink-0 items-center gap-1">
                                            <input type="number" id="acuity_{{ $key }}" name="acuity[{{ $key }}]"
                                                value="{{ old('acuity.' . $key, $fmt($acuitySettings->get($key))) }}"
                                                min="{{ $s::min($key) }}" max="{{ $s::max($key) }}" step="0.1" required
                                                @if ($percent) x-model.number="shares.{{ $code }}" @endif
                                                @disabled(!$canEdit)
                                                class="w-20 rounded-md text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600
                                                    {{ $errors->has('acuity.' . $key) ? 'border-red-400' : ($changed ? 'border-amber-300 bg-amber-50' : 'border-gray-300') }}">
                                            <span class="w-6 text-xs text-gray-500">{{ $percent ? '%' : 'h' }}</span>
                                        </div>
                                    </div>
                                    @error('acuity.' . $key)
                                        <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                        @if (collect(array_keys($fields))->contains(fn (string $key) => str_starts_with($key, 'share_')))
                            <p class="mt-3 text-xs" :class="sharesOk ? 'text-gray-600' : 'font-semibold text-red-700'">
                                The shares add up to <span x-text="Math.round(shareTotal * 10) / 10">{{ $fmt(array_sum($shareValues)) }}</span>%<span x-show="!sharesOk" x-cloak>: they must add up to 100%.</span>
                            </p>
                        @endif
                    </fieldset>
                @endforeach

                {{-- One system for the ward, so the suggestions do not depend on who is looking --}}
                <fieldset class="rounded-lg border border-gray-200 bg-white p-3 md:col-span-2">
                    <legend class="px-1 text-xs font-bold uppercase tracking-wider text-gray-600">Early warning score for the indicators</legend>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label for="acuity_ews_system" class="min-w-0">
                            <span class="block text-sm font-medium text-gray-800">Scoring system</span>
                            <span class="block text-[11px] leading-snug text-gray-500">
                                The ward's own, so everyone sees the same suggested levels; the ward dashboard's setting is each user's
                                &middot; default {{ $s::EWS_SYSTEMS[$s::DEFAULT_EWS_SYSTEM] }}
                            </span>
                        </label>
                        <select id="acuity_ews_system" name="acuity[ews_system]" @disabled(!$canEdit)
                            class="w-full max-w-full rounded-md text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600 sm:w-auto
                                {{ $errors->has('acuity.ews_system') ? 'border-red-400' : ($ewsChanged ? 'border-amber-300 bg-amber-50' : 'border-gray-300') }}">
                            @foreach ($s::EWS_SYSTEMS as $value => $label)
                                <option value="{{ $value }}" @selected($ewsValue === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('acuity.ews_system')
                        <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </fieldset>
            </div>

            <p class="px-4 text-xs text-gray-600">
                <span class="font-semibold">Example:</span> 10 patients at Level 2 need 10 &times; {{ $fmt($acuitySettings->hours(2)) }}
                = {{ $fmt($exampleHours) }} hours of care a day. The AM shift's {{ $fmt($acuitySettings->share('AM')) }}% is
                {{ $fmt(round($exampleShift, 1)) }} h; over a {{ $fmt($exampleLength) }} h shift that is {{ $fmt(round($exampleExact, 2)) }}
                nurses, so <span class="font-semibold">{{ $exampleNurses }} registered {{ \Illuminate\Support\Str::plural('nurse', $exampleNurses) }}</span>.
            </p>

            @if ($canEdit)
                <div class="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 px-4 py-3">
                    <button type="submit" form="reset-acuity-settings"
                        onclick="return confirm('Put the hours of care and the shift shares back to the defaults?')"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-gray-50">
                        Reset to defaults
                    </button>
                    <button type="submit" :disabled="!sharesOk"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Save settings
                    </button>
                </div>
            @else
                <p class="mt-3 border-t border-gray-200 px-4 py-3 text-xs text-gray-500">Nurse managers and admins can change these.</p>
            @endif
        </form>

        @if ($canEdit)
            <form id="reset-acuity-settings" method="POST" action="{{ route('ward.ai-schedule.acuity.settings.reset') }}" class="hidden">
                @csrf
                <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
            </form>
        @endif
    </div>
</div>
