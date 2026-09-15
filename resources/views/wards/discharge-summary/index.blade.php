<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-800">
                    {{ __('Discharge Summary') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    View and print the summary of any admission, discharged or ongoing
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white/90 shadow-lg backdrop-blur-sm">
                <div class="p-6">
                    {{-- Filters. Everything is a GET parameter so a filtered
                         list stays shareable and survives paging. --}}
                    <form method="GET" action="{{ route('discharge-summaries.index') }}" class="mb-6">
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="w-72 max-w-full">
                                <label for="search"
                                    class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Search
                                </label>
                                {{-- The icon is positioned against the input alone, not the
                                     label + input pair, so it stays vertically centred. --}}
                                <div class="relative">
                                    <input type="text" id="search" name="search" value="{{ $filters['search'] }}"
                                        placeholder="Patient name, MRN or bed..." autocomplete="off"
                                        data-lpignore="true" data-form-type="other" data-1p-ignore
                                        class="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-4 transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                    <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                            </div>

                            <div>
                                <label for="ward_id"
                                    class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Ward
                                </label>
                                <select name="ward_id" id="ward_id"
                                    class="rounded-lg border border-gray-300 py-2.5 pl-3 pr-8 focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All wards</option>
                                    @foreach ($wards as $ward)
                                        <option value="{{ $ward->id }}"
                                            {{ (string) $filters['ward_id'] === (string) $ward->id ? 'selected' : '' }}>
                                            {{ $ward->ward_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="status"
                                    class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Status
                                </label>
                                <select name="status" id="status"
                                    class="rounded-lg border border-gray-300 py-2.5 pl-3 pr-8 focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All admissions</option>
                                    <option value="discharged" {{ $filters['status'] === 'discharged' ? 'selected' : '' }}>
                                        Discharged
                                    </option>
                                    <option value="admitted" {{ $filters['status'] === 'admitted' ? 'selected' : '' }}>
                                        Not discharged
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label for="from_date"
                                    class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Admitted from
                                </label>
                                <input type="date" id="from_date" name="from_date" value="{{ $filters['from_date'] }}"
                                    class="rounded-lg border border-gray-300 py-2.5 px-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label for="to_date"
                                    class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Admitted to
                                </label>
                                <input type="date" id="to_date" name="to_date" value="{{ $filters['to_date'] }}"
                                    class="rounded-lg border border-gray-300 py-2.5 px-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            </div>

                            <button type="submit"
                                class="inline-flex items-center rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-200">
                                Apply
                            </button>

                            @if (array_filter($filters))
                                <a href="{{ route('discharge-summaries.index') }}"
                                    class="inline-flex items-center gap-1 px-3 py-2.5 text-sm text-gray-500 transition-colors hover:text-gray-700">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Clear
                                </a>
                            @endif
                        </div>

                        <p class="mt-2 text-sm text-gray-500">
                            <span class="font-semibold text-gray-700">{{ $admissions->total() }}</span>
                            {{ Str::plural('admission', $admissions->total()) }} found
                        </p>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-blue-100">
                            <thead>
                                <tr class="bg-gradient-to-r from-blue-50 to-cyan-50">
                                    <th scope="col"
                                        class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700">
                                        Patient</th>
                                    <th scope="col"
                                        class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700">
                                        Ward / Bed</th>
                                    <th scope="col"
                                        class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700">
                                        Admitted</th>
                                    <th scope="col"
                                        class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700">
                                        Discharged</th>
                                    <th scope="col"
                                        class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700">
                                        Stay</th>
                                    <th scope="col"
                                        class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-700">
                                        Consultant</th>
                                    <th scope="col"
                                        class="px-4 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-gray-700">
                                        Summary</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-blue-50 bg-white">
                                @forelse ($admissions as $admission)
                                    @php $episode = $admission->episode; @endphp
                                    <tr class="transition-colors hover:bg-blue-50">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-800">
                                                {{ $admission->patient?->name ?? $admission->patient_name }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                MRN {{ $admission->mrn ?? '—' }}
                                                @if ($admission->gender || $admission->age)
                                                    &middot; {{ $admission->gender }}
                                                    @if ($admission->age)
                                                        {{ $admission->age }}y
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <div>{{ $admission->ward?->ward_name ?? '—' }}</div>
                                            <div class="text-xs text-gray-500">Bed {{ $admission->bed_number ?? '—' }}
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                            {{ $episode->admittedAt->format('d M Y') }}
                                            <div class="text-xs text-gray-500">
                                                {{ $episode->admittedAt->format('H:i') }}
                                                &middot; {{ $admission->source === 'adt' ? 'ADT' : 'Manual' }}
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                                            @if ($episode->isDischarged())
                                                <span class="text-gray-600">
                                                    {{ $episode->dischargedAt->format('d M Y') }}
                                                </span>
                                                <div class="text-xs text-gray-500">
                                                    {{ $episode->dischargedAt->format('H:i') }}</div>
                                            @else
                                                <span
                                                    class="inline-flex rounded-full border border-amber-200 bg-gradient-to-r from-amber-100 to-orange-100 px-3 py-1 text-xs font-semibold leading-5 text-amber-800">
                                                    Not discharged
                                                </span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                            {{ $episode->lengthOfStay() }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ $admission->consultant_name ?? $admission->patient?->consultant?->name ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium">
                                            <a href="{{ route('discharge-summaries.show', $admission) }}"
                                                class="mr-2 inline-flex items-center rounded-lg bg-blue-100 px-3 py-1.5 text-blue-700 transition-colors hover:bg-blue-200">
                                                <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                View
                                            </a>
                                            <a href="{{ route('discharge-summaries.print', $admission) }}"
                                                target="_blank"
                                                class="inline-flex items-center rounded-lg bg-green-100 px-3 py-1.5 text-green-700 transition-colors hover:bg-green-200">
                                                <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                </svg>
                                                Print
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                                            No admissions match these filters.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($admissions->hasPages())
                        <div class="mt-6">
                            {{ $admissions->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
