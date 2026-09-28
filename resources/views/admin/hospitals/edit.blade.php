@php
    use App\Support\HospitalTheme;
    use App\Support\NavigationMenu;

    // Open the tab whose fields failed validation, else the one asked for in the URL
    $themeErrors = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'theme_') || str_starts_with($key, 'menu'));
    $initialTab = $themeErrors ? 'theme' : ($errors->any() ? 'details' : (request('tab') === 'theme' ? 'theme' : 'details'));

    $oldMenu = old('menu');
    $menuState = collect(NavigationMenu::keys())->mapWithKeys(fn ($key) => [
        $key => is_array($oldMenu) && array_key_exists($key, $oldMenu) ? (bool) $oldMenu[$key] : $menu->shows($key),
    ]);
    $groups = NavigationMenu::groups();
    $previewEntries = collect(NavigationMenu::ENTRIES)->map(fn ($entry, $key) => is_string($entry)
        ? ['key' => $key, 'label' => $entry, 'items' => null]
        : ['key' => $key, 'label' => $entry['label'], 'items' => array_keys($entry['items'])])->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">{{ __('Edit Hospital') }}</h2>
                <p class="mt-1 truncate text-sm text-gray-500">{{ $hospital->name }}</p>
            </div>
            <a href="{{ route('hospitals.index') }}"
                class="inline-flex items-center text-sm font-medium text-gray-500 transition-colors hover:text-brand-600">
                <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                All hospitals
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8" x-data="{
        tab: @js($initialTab),
        primary: @js(old('theme_primary_color', $themeColours[0])),
        secondary: @js(old('theme_secondary_color', $themeColours[1])),
        defaults: { primary: @js(HospitalTheme::DEFAULT_PRIMARY), secondary: @js(HospitalTheme::DEFAULT_SECONDARY) },
        presets: @js(HospitalTheme::PRESETS),
        menu: @js($menuState),
        alwaysShown: @js(NavigationMenu::ALWAYS_SHOWN),
        entries: @js($previewEntries),
        showTab(tab) {
            this.tab = tab;
            const url = new URL(window.location);
            tab === 'theme' ? url.searchParams.set('tab', 'theme') : url.searchParams.delete('tab');
            history.replaceState(null, '', url);
        },
        usePreset(preset) { this.primary = preset.primary; this.secondary = preset.secondary; },
        isPreset(preset) { return this.primary === preset.primary && this.secondary === preset.secondary; },
        setColour(which, value) {
            value = value.trim().toLowerCase();
            if (!value.startsWith('#')) value = '#' + value;
            if (/^#[0-9a-f]{3}$/.test(value)) value = '#' + [...value.slice(1)].map(c => c + c).join('');
            if (/^#[0-9a-f]{6}$/.test(value)) this[which] = value;
            return this[which];
        },
        /* White menu text needs enough contrast: WCAG's 3:1 for the primary colour, and at
           least the default cyan's ~2.4:1 for the lighter end of the gradient */
        contrast(hex) {
            const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255)
                .map(c => c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
            return 1.05 / (0.2126 * r + 0.7152 * g + 0.0722 * b + 0.05);
        },
        get lowContrast() { return this.contrast(this.primary) < 3 || this.contrast(this.secondary) < 2; },
        tint(hex, alpha) {
            const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16));
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        },
        isLocked(key) { return this.alwaysShown.includes(key); },
        shownIn(keys) { return keys.filter(key => this.menu[key]).length; },
        setAll(keys, shown) { keys.forEach(key => { if (!this.isLocked(key)) this.menu[key] = shown; }); },
    }">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-lg border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 text-green-800 shadow-md"
                    role="alert">
                    <div class="flex items-center">
                        <svg class="mr-3 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            <!-- Tabs -->
            <div class="mb-6 border-b border-gray-200">
                <nav class="-mb-px flex gap-2 overflow-x-auto sm:gap-6" role="tablist" aria-label="Hospital settings">
                    @foreach (['details' => 'Hospital Details', 'theme' => 'Theme & Menu'] as $tabKey => $tabLabel)
                        <button type="button" role="tab" id="tab-{{ $tabKey }}" aria-controls="panel-{{ $tabKey }}"
                            :aria-selected="tab === @js($tabKey)" :tabindex="tab === @js($tabKey) ? 0 : -1"
                            @click="showTab(@js($tabKey))"
                            @keydown.arrow-right.prevent="showTab(@js($tabKey) === 'details' ? 'theme' : 'details'); $nextTick(() => document.getElementById('tab-' + tab).focus())"
                            @keydown.arrow-left.prevent="showTab(@js($tabKey) === 'details' ? 'theme' : 'details'); $nextTick(() => document.getElementById('tab-' + tab).focus())"
                            class="group inline-flex shrink-0 items-center gap-2 border-b-2 px-2 py-3 sm:px-3 text-sm font-semibold transition-colors focus:outline-none focus-visible:rounded-t-md focus-visible:bg-brand-50"
                            :class="tab === @js($tabKey) ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'">
                            @if ($tabKey === 'details')
                                <svg class="hidden h-5 w-5 min-[400px]:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            @else
                                <svg class="hidden h-5 w-5 min-[400px]:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                </svg>
                            @endif
                            {{ $tabLabel }}
                            @if (($tabKey === 'theme' && $themeErrors) || ($tabKey === 'details' && $errors->any() && !$themeErrors))
                                <span class="h-2 w-2 rounded-full bg-red-500" title="Has errors"></span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>

            <form method="POST" action="{{ route('hospitals.update', $hospital) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" :value="tab">

                <!-- Hospital Details -->
                <div x-show="tab === 'details'" id="panel-details" role="tabpanel" aria-labelledby="tab-details"
                    @if ($initialTab !== 'details') x-cloak @endif>
                    <div class="overflow-hidden rounded-2xl border border-brand-100 bg-white/90 shadow-lg backdrop-blur-sm">
                        <div class="grid grid-cols-1 gap-6 p-6 text-gray-900 sm:p-8 lg:grid-cols-2">
                            <div class="lg:col-span-2">
                                <label for="name" class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" id="name" value="{{ old('name', $hospital->name) }}" required
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="lg:col-span-2">
                                <label for="logo" class="block text-sm font-medium text-gray-700">Hospital Logo</label>
                                <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center">
                                    @if ($hospital->logo_path)
                                        <div class="inline-flex shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand-600 to-accent-500 p-2">
                                            <img src="{{ Storage::url($hospital->logo_path) }}" alt="Current Logo" class="h-16 w-auto object-contain">
                                        </div>
                                    @endif
                                    <input type="file" name="logo" id="logo" accept="image/*"
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                                </div>
                                @error('logo')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="text" name="phone" id="phone" value="{{ old('phone', $hospital->phone) }}"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" id="email" value="{{ old('email', $hospital->email) }}"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                                <textarea name="address" id="address" rows="4"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('address', $hospital->address) }}</textarea>
                                @error('address')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea name="description" id="description" rows="4"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('description', $hospital->description) }}</textarea>
                                <p class="mt-1 text-xs text-gray-500">Shown under the hospital name on the login page.</p>
                                @error('description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Theme & Menu -->
                <div x-show="tab === 'theme'" id="panel-theme" role="tabpanel" aria-labelledby="tab-theme"
                    @if ($initialTab !== 'theme') x-cloak @endif>
                    <input type="hidden" name="theme_primary_color" :value="primary">
                    <input type="hidden" name="theme_secondary_color" :value="secondary">

                    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-5">
                        <div class="min-w-0 space-y-6 lg:col-span-3">
                            <!-- Theme colour -->
                            <section class="rounded-2xl border border-brand-100 bg-white/90 p-6 shadow-lg backdrop-blur-sm sm:p-8">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-bold text-gray-800">Theme colour</h3>
                                        <p class="mt-1 text-sm text-gray-500">Colours the sidebar, page headers, main buttons and the login page for everyone.</p>
                                    </div>
                                    <button type="button" @click="usePreset(defaults)" x-show="!isPreset(defaults)" x-cloak
                                        class="inline-flex shrink-0 items-center self-start rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900">
                                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Reset to default
                                    </button>
                                </div>

                                <p class="mt-6 text-xs font-semibold uppercase tracking-wider text-gray-500">Presets</p>
                                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <template x-for="preset in presets" :key="preset.name">
                                        <button type="button" @click="usePreset(preset)"
                                            class="group rounded-xl border p-2 text-left transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                            :class="isPreset(preset) ? 'border-gray-900 ring-1 ring-gray-900' : 'border-gray-200 hover:border-gray-300 hover:shadow-md'"
                                            :aria-pressed="isPreset(preset)">
                                            <span class="relative block h-12 rounded-lg"
                                                :style="`background: linear-gradient(135deg, ${preset.primary}, ${preset.secondary})`">
                                                <svg x-show="isPreset(preset)" class="absolute right-1.5 top-1.5 h-5 w-5 rounded-full bg-white p-0.5 text-gray-900 shadow"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </span>
                                            <span class="mt-2 block px-1 text-sm font-medium text-gray-700">
                                                <span x-text="preset.name"></span>
                                                <span x-show="preset.primary === defaults.primary && preset.secondary === defaults.secondary"
                                                    class="text-xs font-normal text-gray-400">(default)</span>
                                            </span>
                                        </button>
                                    </template>
                                </div>

                                <p class="mt-6 text-xs font-semibold uppercase tracking-wider text-gray-500">Custom colours</p>
                                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                    @foreach (['primary' => ['Primary', 'Top of the sidebar and the start of buttons'], 'secondary' => ['Secondary', 'Bottom of the sidebar and the end of buttons']] as $which => [$colourLabel, $colourHint])
                                        <div>
                                            <label for="{{ $which }}-hex" class="block text-sm font-medium text-gray-700">{{ $colourLabel }}</label>
                                            <div class="mt-1 flex items-center gap-2">
                                                <input type="color" x-model="{{ $which }}" aria-label="{{ $colourLabel }} colour picker"
                                                    class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-gray-300 bg-white p-1">
                                                <input type="text" id="{{ $which }}-hex" :value="{{ $which }}" maxlength="7" spellcheck="false"
                                                    @change="$event.target.value = setColour(@js($which), $event.target.value)"
                                                    class="block w-full rounded-lg border-gray-300 font-mono text-sm uppercase shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">{{ $colourHint }}</p>
                                            @error("theme_{$which}_color")
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endforeach
                                </div>

                                <div x-show="lowContrast" x-cloak x-transition
                                    class="mt-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span>The menu text is white and may be hard to read on these colours. A darker shade keeps it legible.</span>
                                </div>
                            </section>

                            <!-- Sidebar menu -->
                            <section class="rounded-2xl border border-brand-100 bg-white/90 p-6 shadow-lg backdrop-blur-sm sm:p-8">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-bold text-gray-800">Sidebar menu</h3>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Choose the items shown in the left menu. People still only see the items their role allows,
                                            and hiding an item doesn't block its page.
                                        </p>
                                    </div>
                                    <span class="shrink-0 self-start rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700"
                                        x-text="`${shownIn(Object.keys(menu))} of ${Object.keys(menu).length} shown`"></span>
                                </div>

                                <div class="mt-6 space-y-4">
                                    @foreach ($groups as $groupKey => $group)
                                        @php($groupKeys = array_keys($group['items']))
                                        <div class="rounded-xl border border-gray-200" x-data="{ keys: @js($groupKeys) }">
                                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-t-xl border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                                                <div class="flex items-center gap-2">
                                                    <h4 class="text-sm font-semibold text-gray-800">{{ $group['label'] }}</h4>
                                                    <span class="text-xs text-gray-500" x-text="`${shownIn(keys)}/${keys.length}`"></span>
                                                </div>
                                                <div class="flex items-center gap-1 text-xs font-medium">
                                                    <button type="button" @click="setAll(keys, true)"
                                                        class="rounded px-2 py-1 text-brand-700 hover:bg-brand-50">Show all</button>
                                                    <button type="button" @click="setAll(keys, false)"
                                                        class="rounded px-2 py-1 text-gray-600 hover:bg-gray-200">Hide all</button>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 divide-y divide-gray-100 sm:grid-cols-2 sm:divide-y-0">
                                                @foreach ($group['items'] as $itemKey => $itemLabel)
                                                    @if (in_array($itemKey, NavigationMenu::ALWAYS_SHOWN, true))
                                                        <div class="flex items-center justify-between gap-3 px-4 py-3" title="Always shown, so this page stays reachable from the menu">
                                                            <span class="flex min-w-0 items-center gap-1.5 text-sm text-gray-700">
                                                                <span class="truncate">{{ $itemLabel }}</span>
                                                                <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                                </svg>
                                                            </span>
                                                            <span class="relative inline-flex h-6 w-11 shrink-0 cursor-not-allowed items-center rounded-full bg-brand-600 opacity-50">
                                                                <span class="inline-block h-5 w-5 translate-x-5 rounded-full bg-white shadow"></span>
                                                            </span>
                                                        </div>
                                                    @else
                                                        <label class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50">
                                                            <span class="min-w-0 truncate text-sm" :class="menu[@js($itemKey)] ? 'text-gray-700' : 'text-gray-400 line-through'">{{ $itemLabel }}</span>
                                                            <input type="hidden" name="menu[{{ $itemKey }}]" value="0">
                                                            <input type="checkbox" name="menu[{{ $itemKey }}]" value="1" x-model="menu[@js($itemKey)]" class="peer sr-only">
                                                            <span class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-gray-300 transition-colors peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2">
                                                                <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"
                                                                    :class="menu[@js($itemKey)] ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                                            </span>
                                                        </label>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        </div>

                        <!-- Live preview -->
                        <aside class="min-w-0 lg:sticky lg:top-6 lg:col-span-2">
                            <div class="rounded-2xl border border-brand-100 bg-white/90 p-5 shadow-lg backdrop-blur-sm">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500">Preview</h3>
                                    <span class="text-xs text-gray-400">Applies to everyone when saved</span>
                                </div>

                                <div class="mt-4 flex h-[26rem] overflow-hidden rounded-xl border border-gray-200 shadow-inner"
                                    :style="`background: linear-gradient(135deg, ${tint(primary, 0.06)}, ${tint(secondary, 0.08)}), #fff`">
                                    <!-- Sidebar -->
                                    <div class="flex w-44 shrink-0 flex-col text-white sm:w-48"
                                        :style="`background: linear-gradient(to bottom right, ${primary}, ${secondary})`">
                                        <div class="flex h-11 items-center border-b border-white/20 bg-white/10 px-3">
                                            <span class="truncate text-xs font-bold">{{ $hospital->name }}</span>
                                        </div>
                                        <div class="flex-1 space-y-0.5 overflow-y-auto p-2 [scrollbar-width:none]">
                                            <template x-for="entry in entries" :key="entry.key">
                                                <div x-show="entry.items ? shownIn(entry.items) > 0 : menu[entry.key]"
                                                    class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[11px] font-medium"
                                                    :class="entry.key === 'admin' ? 'bg-white/25' : ''">
                                                    <span class="h-3 w-3 shrink-0 rounded bg-white/40"></span>
                                                    <span class="flex-1 truncate" x-text="entry.label"></span>
                                                    <span x-show="entry.items" class="rounded bg-white/20 px-1 text-[10px]" x-text="entry.items && shownIn(entry.items)"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Page -->
                                    <div class="flex min-w-0 flex-1 flex-col">
                                        <div class="flex h-11 items-center border-b bg-white/80 px-3"
                                            :style="`border-color: ${tint(primary, 0.15)}`">
                                            <span class="truncate text-xs font-bold text-gray-800">Edit Hospital</span>
                                        </div>
                                        <div class="space-y-3 p-3">
                                            <div class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-100">
                                                <div class="h-2 w-3/4 rounded bg-gray-200"></div>
                                                <div class="mt-2 h-2 w-1/2 rounded bg-gray-100"></div>
                                                <div class="mt-3 inline-flex rounded-md px-2.5 py-1 text-[10px] font-semibold text-white shadow"
                                                    :style="`background: linear-gradient(to right, ${primary}, ${secondary})`">Save</div>
                                            </div>
                                            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                                                <div class="h-5" :style="`background: linear-gradient(to right, ${tint(primary, 0.1)}, ${tint(secondary, 0.1)})`"></div>
                                                <div class="space-y-1.5 p-3">
                                                    <div class="h-2 rounded bg-gray-100"></div>
                                                    <div class="h-2 rounded bg-gray-100"></div>
                                                    <div class="h-2 w-2/3 rounded bg-gray-100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <p class="mt-3 text-xs text-gray-500">Numbers show how many items each section keeps.</p>
                            </div>
                        </aside>
                    </div>
                </div>

                <!-- Actions -->
                <div class="sticky bottom-0 z-10 -mx-4 mt-6 border-t border-gray-200 bg-white/85 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:mx-0 lg:rounded-2xl lg:border lg:px-6 lg:shadow-lg">
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('hospitals.index') }}"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-gray-100 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition-colors hover:bg-gray-200">
                            Cancel
                        </a>
                        <button type="submit"
                            class="inline-flex items-center rounded-lg border border-transparent bg-gradient-to-r from-brand-600 to-accent-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:from-brand-700 hover:to-accent-700 hover:shadow-xl">
                            Save changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
