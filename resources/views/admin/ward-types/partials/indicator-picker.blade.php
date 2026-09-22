{{--
    Clinical indicator checkboxes for the ward type forms, one row per
    category. Expects $indicatorGroups (ClinicalIndicatorLibrary::groupByCategory)
    and $selectedIds.
--}}

<div class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-gray-50">
    @forelse ($indicatorGroups as $category => $indicators)
        @php
            $groupId = 'indicator-group-' . \Illuminate\Support\Str::slug($category);
        @endphp
        <div role="group" aria-labelledby="{{ $groupId }}" class="p-3 sm:flex sm:items-start sm:gap-4">
            <div id="{{ $groupId }}" class="mb-2 flex items-center gap-2 sm:mb-0 sm:w-48 sm:shrink-0 sm:pt-2">
                <x-clinical-indicator-category-icon :category="$category" size="sm" />
                <span class="text-sm font-semibold text-gray-700">{{ $category }}</span>
            </div>
            <div class="flex flex-1 flex-wrap gap-2">
                @foreach ($indicators as $indicator)
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 transition-colors hover:border-blue-300 hover:bg-blue-50 has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50">
                        <input type="checkbox" name="clinical_indicator_ids[]" value="{{ $indicator->id }}"
                            @checked(in_array($indicator->id, $selectedIds))
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-800">{{ $indicator->name }}</span>
                        <span class="text-xs text-gray-500">{{ $indicator->code }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @empty
        <p class="py-4 text-center text-sm text-gray-500">No active clinical indicators available.</p>
    @endforelse
</div>
