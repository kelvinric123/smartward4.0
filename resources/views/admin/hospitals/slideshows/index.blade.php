@php
    // Every setting on the page saves through this one form; inputs outside it join it with form="..."
    $settingsForm = 'slideshow-settings';
    $slideshowOn = (bool) old('slideshow_enabled', $hospital->slideshowEnabled());
    $cardClass = 'bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-brand-100';
    $trackClass = 'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-gray-300 transition-colors peer-checked:bg-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="{{ route('hospitals.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                        {{ __('Manage Slideshow') }} - {{ $hospital->name }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">The slideshow the ward dashboards open from the hospital logo</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ dirty: false }"
        @change="if ($event.target.form && $event.target.form.id === @js($settingsForm)) dirty = true"
        @input="if ($event.target.form && $event.target.form.id === @js($settingsForm)) dirty = true">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            @if (session('success'))
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Settings -->
            <form id="{{ $settingsForm }}" method="POST" action="{{ route('hospitals.slideshows.settings', $hospital) }}">
                @csrf
                @method('PUT')
                <section class="{{ $cardClass }} p-6 sm:p-8" x-data="{ on: @js($slideshowOn) }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-gray-800">Ward dashboard link</h3>
                            <p class="mt-1 text-sm text-gray-500">The hospital logo at the top left of the Ward Dashboard and the Critical Care Dashboard.</p>
                        </div>
                        <span class="shrink-0 rounded-full px-3 py-1 text-sm font-semibold"
                            :class="on ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                            x-text="on ? 'On' : 'Off'">{{ $slideshowOn ? 'On' : 'Off' }}</span>
                    </div>

                    <label class="mt-6 flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-gray-200 px-4 py-4 hover:bg-gray-50">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-800">Open the slideshow from the logo</span>
                            <span class="mt-0.5 block text-sm text-gray-500" x-show="on" @if (! $slideshowOn) x-cloak @endif>
                                Clicking the logo opens the slideshow full screen.
                            </span>
                            <span class="mt-0.5 block text-sm text-gray-500" x-show="!on" @if ($slideshowOn) x-cloak @endif>
                                The logo doesn't open the slideshow; clicking it refreshes the dashboard, like the title.
                            </span>
                        </span>
                        <input type="hidden" name="slideshow_enabled" value="0">
                        <input type="checkbox" name="slideshow_enabled" value="1" x-model="on" class="peer sr-only" @checked($slideshowOn)>
                        <span class="{{ $trackClass }}">
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"
                                :class="on ? 'translate-x-5' : 'translate-x-0.5'"></span>
                        </span>
                    </label>

                    <div class="mt-6">
                        <label for="slideshow_seconds" class="block text-sm font-medium text-gray-700">Seconds per slide</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="number" name="slideshow_seconds" id="slideshow_seconds" required step="1"
                                min="{{ \App\Models\Hospital::MIN_SLIDESHOW_SECONDS }}" max="{{ \App\Models\Hospital::MAX_SLIDESHOW_SECONDS }}"
                                value="{{ old('slideshow_seconds', $hospital->slideshowSeconds()) }}"
                                class="block w-28 rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-500">seconds</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ \App\Models\Hospital::MIN_SLIDESHOW_SECONDS }} to {{ \App\Models\Hospital::MAX_SLIDESHOW_SECONDS }}.
                            Anyone watching can still pause the slideshow or step through it.
                        </p>
                        @error('slideshow_seconds')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </section>
            </form>

            <!-- Upload -->
            <div class="{{ $cardClass }}">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Upload New File</h3>
                    <form action="{{ route('hospitals.slideshows.store', $hospital) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <label for="file" class="block text-sm font-medium text-gray-700">File (Image/PDF, max 50MB)</label>
                                <input type="file" name="file" id="file" accept=".jpg,.jpeg,.png,.pdf" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            </div>
                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700">Name <span class="font-normal text-gray-400">(optional)</span></label>
                                <input type="text" name="title" id="title" maxlength="100" placeholder="The file's name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div>
                                <label for="ward_id" class="block text-sm font-medium text-gray-700">Target Ward</label>
                                <select name="ward_id" id="ward_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">All Wards (Hospital-wide)</option>
                                    @foreach($hospital->wards as $ward)
                                        <option value="{{ $ward->id }}">{{ $ward->ward_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-700 hover:to-accent-700 border border-transparent rounded-lg font-semibold text-white shadow-md transition-all">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                    Upload
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Uploaded slides -->
            <div class="{{ $cardClass }}">
                <div class="p-6">
                    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-lg font-semibold text-gray-800">Uploaded slides</h3>
                        <p class="text-sm text-gray-500">Played first, lowest order number first</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        @forelse ($slides as $slide)
                            @php($slideOn = (bool) old("slides.{$slide->id}.active", $slide->is_active))
                            <div class="border rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow bg-white flex flex-col" x-data="{ on: @js($slideOn) }">
                                <div class="h-40 bg-gray-100 flex items-center justify-center overflow-hidden relative group transition-opacity" :class="on ? '' : 'opacity-40'">
                                    @if(str_contains($slide->file_type, 'pdf'))
                                        <svg class="w-16 h-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="absolute bottom-2 right-2 text-xs font-bold text-gray-500 bg-white/80 px-2 py-1 rounded">PDF</span>
                                    @else
                                        <img src="{{ Storage::url($slide->file_path) }}" alt="{{ $slide->displayName() }}" loading="lazy" class="object-cover w-full h-full">
                                    @endif
                                </div>
                                <div class="p-4 flex-1 flex flex-col gap-3 border-t border-gray-100">
                                    <div>
                                        <label for="slide-{{ $slide->id }}-title" class="block text-xs font-medium text-gray-500 mb-1">Name</label>
                                        <input type="text" form="{{ $settingsForm }}" name="slides[{{ $slide->id }}][title]" id="slide-{{ $slide->id }}-title" maxlength="100"
                                            value="{{ old("slides.{$slide->id}.title", $slide->title) }}" placeholder="{{ $slide->displayName() }}"
                                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 mb-1">Target</p>
                                        @if($slide->ward_id)
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-md text-xs font-semibold">{{ $slide->ward?->ward_name }}</span>
                                        @else
                                            <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-md text-xs font-semibold">All Wards</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-end justify-between gap-x-3 gap-y-2">
                                        <div>
                                            <label for="slide-{{ $slide->id }}-order" class="block text-xs font-medium text-gray-500 mb-1">Order</label>
                                            <input type="number" form="{{ $settingsForm }}" name="slides[{{ $slide->id }}][order]" id="slide-{{ $slide->id }}-order" min="0" max="9999" step="1"
                                                value="{{ old("slides.{$slide->id}.order", $slide->order) }}"
                                                class="block w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                        </div>
                                        <label class="flex cursor-pointer items-center gap-2 pb-1.5">
                                            <span class="text-sm font-medium" :class="on ? 'text-gray-700' : 'text-gray-400'" x-text="on ? 'Showing' : 'Hidden'">{{ $slideOn ? 'Showing' : 'Hidden' }}</span>
                                            <input type="hidden" form="{{ $settingsForm }}" name="slides[{{ $slide->id }}][active]" value="0">
                                            <input type="checkbox" form="{{ $settingsForm }}" name="slides[{{ $slide->id }}][active]" value="1" x-model="on" class="peer sr-only"
                                                @checked($slideOn) aria-label="Show {{ $slide->displayName() }} in the slideshow">
                                            <span class="{{ $trackClass }}">
                                                <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"
                                                    :class="on ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="mt-auto pt-1">
                                        <form action="{{ route('hospitals.slideshows.destroy', [$hospital, $slide]) }}" method="POST" >
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" onclick="confirmDelete(event, 'Are you sure you want to delete this item?')" class="w-full inline-flex justify-center items-center px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-sm font-medium transition-colors border border-red-200">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full py-12 text-center">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-gray-500 font-medium">No slideshow files uploaded yet</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Built-in pictures -->
            @if ($pictures->isNotEmpty())
                <div class="{{ $cardClass }}">
                    <div class="p-6">
                        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                            <h3 class="text-lg font-semibold text-gray-800">Built-in pictures</h3>
                            <p class="text-sm text-gray-500">Kept on the server and played after the uploaded slides, for every ward</p>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach ($pictures as $index => $picture)
                                @php($pictureOn = (bool) old("pictures.{$index}.shown", $picture['shown']))
                                <label class="border rounded-xl overflow-hidden shadow-sm bg-white flex flex-col cursor-pointer hover:shadow-md transition-shadow" x-data="{ on: @js($pictureOn) }">
                                    <span class="h-28 bg-gray-100 flex items-center justify-center overflow-hidden transition-opacity" :class="on ? '' : 'opacity-40'">
                                        @if ($picture['pdf'])
                                            <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                        @else
                                            <img src="{{ route('picture.serve', ['filename' => $picture['file']]) }}" alt="{{ $picture['name'] }}" loading="lazy" class="object-cover w-full h-full">
                                        @endif
                                    </span>
                                    <span class="flex items-center justify-between gap-2 border-t border-gray-100 px-3 py-2.5">
                                        <span class="min-w-0 truncate text-sm font-medium" :class="on ? 'text-gray-800' : 'text-gray-400 line-through'" title="{{ $picture['name'] }}">{{ $picture['name'] }}</span>
                                        <input type="hidden" form="{{ $settingsForm }}" name="pictures[{{ $index }}][file]" value="{{ $picture['file'] }}">
                                        <input type="hidden" form="{{ $settingsForm }}" name="pictures[{{ $index }}][shown]" value="0">
                                        <input type="checkbox" form="{{ $settingsForm }}" name="pictures[{{ $index }}][shown]" value="1" x-model="on" class="peer sr-only"
                                            @checked($pictureOn) aria-label="Show {{ $picture['name'] }} in the slideshow">
                                        <span class="{{ $trackClass }}">
                                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"
                                                :class="on ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Save -->
            <div class="sticky bottom-0 z-10 -mx-4 border-t border-gray-200 bg-white/85 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:mx-0 lg:rounded-2xl lg:border lg:px-6 lg:shadow-lg">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <span x-show="dirty" x-cloak class="mr-auto text-sm font-medium text-amber-700">Unsaved changes</span>
                    <button type="submit" form="{{ $settingsForm }}"
                        class="inline-flex items-center rounded-lg border border-transparent bg-gradient-to-r from-brand-600 to-accent-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:from-brand-700 hover:to-accent-700 hover:shadow-xl">
                        Save settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
