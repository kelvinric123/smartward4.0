@props([
    'route',
    'value' => '',
    'placeholder' => 'Search...',
    'paginator' => null,
    'noun' => 'results',
])

{{--
    Server-side search box for the paginated admin lists.

    Submits to the list's own route so the query runs against the whole table.
    The earlier version filtered the rendered rows in JavaScript, which could
    only ever find records that were already on the current page.
--}}
<form method="GET" action="{{ $route }}" class="mb-6">
    <div class="flex flex-wrap items-center gap-2">
        {{-- The width belongs on the wrapper, not the input. As a flex item
             the wrapper sizes to its content, so an input set to w-full inside
             it resolves against nothing and spills over the Search button. --}}
        <div class="relative w-80 max-w-full">
            <input type="text" id="listSearchInput" name="search" value="{{ $value }}" placeholder="{{ $placeholder }}"
                autocomplete="off" data-lpignore="true" data-form-type="other" data-1p-ignore
                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>

        <button type="submit"
            class="inline-flex items-center px-4 py-2.5 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg font-medium text-sm text-gray-700 shadow-sm transition-colors">
            Search
        </button>

        @if ($value !== '')
            <a href="{{ $route }}"
                class="inline-flex items-center gap-1 px-3 py-2.5 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                title="Clear search">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Clear
            </a>
        @endif
    </div>

    @if ($value !== '' && $paginator)
        <p class="mt-2 text-sm text-gray-500">
            <span class="font-semibold text-gray-700">{{ $paginator->total() }}</span>
            {{ Str::plural(Str::singular($noun), $paginator->total()) }}
            matching <span class="font-medium text-gray-700">"{{ $value }}"</span>
        </p>
    @endif
</form>
