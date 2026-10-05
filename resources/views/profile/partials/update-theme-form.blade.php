{{--
    Profile -> Appearance: how SmartWard looks for this user (App\Support\UserTheme).
    A display mode, tried on live before it is saved, and three colours: the
    hospital's theme, a preset or their own.
--}}
@php
    use App\Support\HospitalTheme;
    use App\Support\UserTheme;

    $themeHospital = \App\Models\Hospital::first();
    $themePreferences = UserTheme::preferences($user);
    [$hospitalPrimary, $hospitalSecondary] = HospitalTheme::colours($themeHospital);
    [$currentPrimary, $currentSecondary, $currentBackground] = UserTheme::colours($user, $themeHospital);
    $themeErrors = $errors->updateTheme;

    // Each mode's page, card, ink and faint lines, for its swatch and the preview
    $modeLooks = [
        UserTheme::MODE_LIGHT => ['page' => '#f8fafc', 'card' => '#ffffff', 'ink' => '#1f2937', 'faint' => '#d1d5db'],
        UserTheme::MODE_DARK => ['page' => '#0f1724', 'card' => '#1b2433', 'ink' => '#e2e8f0', 'faint' => '#3a4659'],
        UserTheme::MODE_SOL => ['page' => '#eee8d5', 'card' => '#fdf6e3', 'ink' => '#073642', 'faint' => '#d8d0b6'],
    ];

    $colourFields = [
        'primary' => ['Primary', 'Top of the sidebar, page headers and the start of buttons'],
        'secondary' => ['Secondary', 'Bottom of the sidebar and the end of buttons'],
        'background' => ['Background', 'The tint of the page behind everything'],
    ];
@endphp

<section x-data="{
    mode: @js(old('mode', $themePreferences['mode'])),
    colours: @js(old('colours', $themePreferences['colours'])),
    custom: @js([
        'primary' => old('primary', $currentPrimary),
        'secondary' => old('secondary', $currentSecondary),
        'background' => old('background', $currentBackground),
    ]),
    hospital: @js(['primary' => $hospitalPrimary, 'secondary' => $hospitalSecondary, 'background' => $hospitalPrimary]),
    presets: @js(UserTheme::PRESETS),
    looks: @js($modeLooks),
    init() { this.preview(); },
    /* The mode the page shows: System follows the device */
    get shown() {
        if (this.mode !== 'system') return this.mode;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    },
    get look() { return this.looks[this.shown]; },
    /* Tried on at once; Save keeps it */
    preview() { document.documentElement.setAttribute('data-theme-mode', this.shown); },
    /* The three colours the current choice gives */
    get active() {
        if (this.colours === 'custom') return this.custom;
        if (this.colours === 'hospital') return this.hospital;
        return this.presets[this.colours];
    },
    choose(choice) {
        this.colours = choice;
        /* Custom starts from whatever was picked last */
        if (choice !== 'custom') this.custom = { primary: this.active.primary, secondary: this.active.secondary, background: this.active.background };
    },
    setColour(which, value) {
        value = value.trim().toLowerCase();
        if (!value.startsWith('#')) value = '#' + value;
        if (/^#[0-9a-f]{3}$/.test(value)) value = '#' + [...value.slice(1)].map(c => c + c).join('');
        if (/^#[0-9a-f]{6}$/.test(value)) {
            this.custom[which] = value;
            this.colours = 'custom';
        }
        return this.custom[which];
    },
    /* White menu text needs enough contrast, as on the hospital's Theme & Menu tab */
    contrast(hex) {
        const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255)
            .map(c => c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
        return 1.05 / (0.2126 * r + 0.7152 * g + 0.0722 * b + 0.05);
    },
    get lowContrast() { return this.contrast(this.active.primary) < 3 || this.contrast(this.active.secondary) < 2; },
    tint(hex, alpha) {
        const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16));
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    },
    stripes(colours) {
        return `linear-gradient(135deg, ${colours.primary}, ${colours.secondary})`;
    },
}">
    <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-medium text-gray-900">{{ __('Appearance') }}</h2>
            <p class="mt-1 text-sm text-gray-600">
                How SmartWard looks for you, wherever you sign in. Everyone else keeps their own, and the
                hospital's theme stays the default.
            </p>
        </div>
        @if (session('status') === 'theme-updated')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)"
                class="shrink-0 text-sm font-medium text-green-700">{{ __('Saved.') }}</p>
        @endif
    </header>

    <form method="post" action="{{ route('profile.theme.update') }}" class="mt-6">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="min-w-0 space-y-8 lg:col-span-2">
                {{-- Display mode --}}
                <fieldset>
                    <legend class="text-sm font-semibold text-gray-800">Mode</legend>
                    <p class="text-xs text-gray-500">The page changes as you pick one, so you can try it first.</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach (UserTheme::MODES as $modeKey => $modeInfo)
                            <label class="cursor-pointer">
                                <input type="radio" name="mode" value="{{ $modeKey }}" x-model="mode" @change="preview()" class="peer sr-only">
                                <span class="block h-full rounded-xl border p-2 transition-all peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500"
                                    :class="mode === @js($modeKey) ? 'border-brand-500 ring-2 ring-brand-500' : 'border-gray-200 hover:border-gray-300 hover:shadow-md'">
                                    {{-- A tiny page in this mode, in the chosen colours --}}
                                    <span class="flex h-16 overflow-hidden rounded-lg border border-gray-300/60" aria-hidden="true">
                                        @foreach ($modeKey === UserTheme::MODE_SYSTEM ? [UserTheme::MODE_LIGHT, UserTheme::MODE_DARK] : [$modeKey] as $lookKey)
                                            @php($look = $modeLooks[$lookKey])
                                            <span class="flex min-w-0 flex-1">
                                                @if ($loop->first)
                                                    <span class="w-2.5 shrink-0" :style="`background: linear-gradient(180deg, ${active.primary}, ${active.secondary})`"></span>
                                                @endif
                                                <span class="flex-1 p-1.5" style="background: {{ $look['page'] }}">
                                                    <span class="block h-full rounded p-1.5" style="background: {{ $look['card'] }}">
                                                        <span class="block h-1.5 w-3/4 rounded-full" style="background: {{ $look['ink'] }}"></span>
                                                        <span class="mt-1 block h-1 w-1/2 rounded-full" style="background: {{ $look['faint'] }}"></span>
                                                        <span class="mt-1.5 block h-2 w-1/3 rounded-sm" :style="`background: ${stripes(active)}`"></span>
                                                    </span>
                                                </span>
                                            </span>
                                        @endforeach
                                    </span>
                                    <span class="mt-2 flex items-center gap-1.5 px-1 text-sm font-medium text-gray-800">
                                        {{ $modeInfo['label'] }}
                                        <svg x-show="mode === @js($modeKey)" x-cloak class="h-4 w-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                    <span class="block px-1 text-xs leading-snug text-gray-500">{{ $modeInfo['hint'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$themeErrors->get('mode')" class="mt-2" />
                </fieldset>

                {{-- Colours: the hospital's, a preset, or custom --}}
                <fieldset>
                    <legend class="text-sm font-semibold text-gray-800">Colours</legend>
                    <p class="text-xs text-gray-500">Three colours each: primary and secondary for the sidebar, headers and buttons, then the page background.</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ([UserTheme::COLOURS_HOSPITAL => 'Hospital theme', ...array_map(fn ($preset) => $preset['name'], UserTheme::PRESETS), UserTheme::COLOURS_CUSTOM => 'Custom'] as $choice => $choiceName)
                            @php($swatch = match ($choice) { UserTheme::COLOURS_HOSPITAL => 'hospital', UserTheme::COLOURS_CUSTOM => 'custom', default => 'presets[' . \Illuminate\Support\Js::from($choice) . ']' })
                            <label class="cursor-pointer">
                                <input type="radio" name="colours" value="{{ $choice }}" :checked="colours === @js($choice)"
                                    @change="choose(@js($choice))" class="peer sr-only">
                                <span class="block rounded-xl border p-2 transition-all peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500"
                                    :class="colours === @js($choice) ? 'border-brand-500 ring-2 ring-brand-500' : 'border-gray-200 hover:border-gray-300 hover:shadow-md'">
                                    <span class="block overflow-hidden rounded-lg border border-gray-300/60" aria-hidden="true">
                                        <span class="block h-8" :style="`background: ${stripes({{ $swatch }})}`"></span>
                                        <span class="block h-3" :style="`background: ${ {{ $swatch }}.background }`"></span>
                                    </span>
                                    <span class="mt-2 block px-1 text-sm font-medium text-gray-700">
                                        {{ $choiceName }}
                                        @if ($choice === UserTheme::COLOURS_HOSPITAL)
                                            <span class="text-xs font-normal text-gray-400">(default)</span>
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$themeErrors->get('colours')" class="mt-2" />

                    <p class="mt-6 text-xs font-semibold uppercase tracking-wider text-gray-500">Your own colours</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                        @foreach ($colourFields as $which => [$fieldLabel, $fieldHint])
                            <div>
                                <label for="theme-{{ $which }}" class="block text-sm font-medium text-gray-700">{{ $fieldLabel }}</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input type="color" :value="custom.{{ $which }}" @input="setColour(@js($which), $event.target.value)"
                                        aria-label="{{ $fieldLabel }} colour picker"
                                        class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-gray-300 bg-white p-1">
                                    <input type="text" id="theme-{{ $which }}" name="{{ $which }}" :value="custom.{{ $which }}"
                                        maxlength="7" spellcheck="false" autocomplete="off"
                                        @change="$event.target.value = setColour(@js($which), $event.target.value)"
                                        class="block w-full rounded-lg border-gray-300 font-mono text-sm uppercase shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                </div>
                                <p class="mt-1 text-xs text-gray-500">{{ $fieldHint }}</p>
                                <x-input-error :messages="$themeErrors->get($which)" class="mt-1" />
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Changing one switches to Custom, starting from the colours picked above.</p>

                    <div x-show="lowContrast" x-cloak
                        class="mt-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>The menu text is white and may be hard to read on these colours. A darker primary or secondary keeps it legible.</span>
                    </div>
                </fieldset>
            </div>

            {{-- What it will look like --}}
            <div class="lg:sticky lg:top-4 lg:self-start">
                <p class="text-sm font-semibold text-gray-800">Preview</p>
                <div class="mt-3 flex h-56 overflow-hidden rounded-xl border border-gray-300/60 shadow-md" aria-hidden="true">
                    <div class="flex w-14 shrink-0 flex-col gap-2 p-2" :style="`background: linear-gradient(180deg, ${active.primary}, ${active.secondary})`">
                        <span class="h-5 rounded bg-white/30"></span>
                        <span class="h-2 rounded-full bg-white/60"></span>
                        <span class="h-2 rounded-full bg-white/40"></span>
                        <span class="h-2 rounded-full bg-white/40"></span>
                    </div>
                    <div class="flex-1 p-3"
                        :style="`background-color: ${look.page}; background-image: linear-gradient(135deg, ${tint(active.background, shown === 'dark' ? 0.22 : 0.12)}, ${tint(active.secondary, shown === 'dark' ? 0.12 : 0.06)}, ${tint(active.background, shown === 'dark' ? 0.22 : 0.12)})`">
                        <div class="h-full rounded-lg p-3 shadow" :style="`background: ${look.card}`">
                            <span class="block h-2.5 w-2/3 rounded-full" :style="`background: ${look.ink}`"></span>
                            <span class="mt-2 block h-1.5 w-full rounded-full" :style="`background: ${look.faint}`"></span>
                            <span class="mt-1.5 block h-1.5 w-5/6 rounded-full" :style="`background: ${look.faint}`"></span>
                            <span class="mt-1.5 block h-1.5 w-3/4 rounded-full" :style="`background: ${look.faint}`"></span>
                            <span class="mt-4 inline-block rounded-md px-3 py-1.5 text-[10px] font-semibold text-white" :style="`background: ${stripes(active)}`">Save</span>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    <span x-text="{{ \Illuminate\Support\Js::from(collect(UserTheme::MODES)->map(fn ($mode) => $mode['label'])) }}[mode]"></span> mode,
                    <span x-text="colours === 'hospital' ? 'hospital theme' : (colours === 'custom' ? 'your own colours' : presets[colours].name)"></span>.
                    Print-outs stay on white paper.
                </p>

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </div>
            </div>
        </div>
    </form>
</section>
