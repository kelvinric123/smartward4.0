{{-- Theme & Menu tab: the text at the bottom left of every page (layouts/app's footer). Reads the edit page's Alpine state. --}}
<section class="rounded-2xl border border-brand-100 bg-white/90 p-6 shadow-lg backdrop-blur-sm sm:p-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-lg font-bold text-gray-800">Bottom bar</h3>
            <p class="mt-1 text-sm text-gray-500">The name at the bottom left of every page and of the Command Centre.</p>
        </div>
        <button type="button" @click="footerHighlight = footerDefaults.highlight; footerText = footerDefaults.text" x-show="!footerIsDefault" x-cloak
            class="inline-flex shrink-0 items-center self-start rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900">
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Reset to default
        </button>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-5">
        <div class="sm:col-span-2">
            <label for="footer_highlight" class="block text-sm font-medium text-gray-700">Highlighted part</label>
            <input type="text" name="footer_highlight" id="footer_highlight" x-model="footerHighlight" maxlength="40"
                placeholder="{{ \App\Models\Hospital::DEFAULT_FOOTER_HIGHLIGHT }}" autocomplete="off"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="mt-1 text-xs text-gray-500">Bold, in the primary colour</p>
            @error('footer_highlight')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="sm:col-span-3">
            <label for="footer_text" class="block text-sm font-medium text-gray-700">Text after it</label>
            <input type="text" name="footer_text" id="footer_text" x-model="footerText" maxlength="80"
                placeholder="{{ \App\Models\Hospital::DEFAULT_FOOTER_TEXT }}" autocomplete="off"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="mt-1 text-xs text-gray-500">Leave both empty to go back to the default</p>
            @error('footer_text')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <p class="mt-6 text-xs font-semibold uppercase tracking-wider text-gray-500">Preview</p>
    <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 px-4 py-3"
        :style="`background: linear-gradient(135deg, ${tint(primary, 0.06)}, ${tint(secondary, 0.08)}), #fff`">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0 truncate text-sm text-gray-600">
                <span class="font-semibold" :style="`color: ${primary}`" x-text="footerShown[0]"></span>
                <span x-text="footerShown[1]"></span>
            </div>
            <div class="flex shrink-0 items-center space-x-2">
                <span class="hidden text-xs text-gray-500 min-[400px]:inline">Developed by</span>
                <img src="{{ asset('logo_qmed.png') }}" alt="Qmed" class="h-5 w-auto">
            </div>
        </div>
    </div>
</section>
