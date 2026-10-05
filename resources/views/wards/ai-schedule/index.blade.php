{{--
    Schedule > AI Nurse Schedule. The upgraded nurse roster: a staff roster
    planned by AI, beds shared out by workload, workload and fairness, and
    leave. Bed assignments are saved to the same table as the Ward Schedule
    page, so they show on the ward dashboard.
--}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center gap-2">
                    <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                    AI Nurse Schedule
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Plan the nurse roster, share beds out by workload and keep track of leave. Bed assignments saved
                    here show on the ward dashboard.
                </p>
            </div>
            @if ($ward)
                <a href="{{ route('ward.schedule', ['ward_id' => $ward->id]) }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
                    </svg>
                    Ward Schedule
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6" x-data="{ tab: @js($tab ?? 'roster') }">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if (!$ward)
                <div class="bg-white rounded-2xl shadow-lg border border-blue-100 p-10 text-center text-gray-500">
                    No active wards yet. Add a ward under Ward Management first.
                </div>
            @else
                @if (session('success'))
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-4 py-3 rounded-lg shadow-sm text-sm font-medium" role="alert">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-4 py-3 rounded-lg shadow-sm text-sm font-medium" role="alert">
                        {{ session('error') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-4 py-3 rounded-lg shadow-sm text-sm" role="alert">
                        @foreach ($errors->all() as $message)
                            <p>{{ $message }}</p>
                        @endforeach
                    </div>
                @endif
                @if (!empty(session('roster_shortfalls')))
                    <div class="bg-amber-50 border-l-4 border-amber-400 text-amber-900 px-4 py-3 rounded-lg shadow-sm text-sm" role="alert">
                        <p class="font-semibold">Not enough nurses free to meet every minimum:</p>
                        <ul class="mt-1 list-disc list-inside">
                            @foreach (session('roster_shortfalls') as $gap)
                                <li>{{ \Illuminate\Support\Carbon::parse($gap['date'])->format('D j M') }} {{ $gap['shift'] }}: {{ $gap['missing'] }} short</li>
                            @endforeach
                        </ul>
                        <p class="mt-1 text-xs">Leave, the rest rules and the weekly limits left nobody else. Lower the minimum, move leave, or add nurses to the ward.</p>
                    </div>
                @endif

                {{-- Ward and week --}}
                @php
                    $weekEnd = $weekStart->copy()->addDays(6);
                    $weekLink = fn ($start) => route('ward.ai-schedule', ['ward_id' => $ward->id, 'week' => $start->toDateString(), 'tab' => $tab]);
                @endphp
                <div class="bg-white rounded-2xl shadow-lg border border-blue-100 px-5 py-4 flex flex-wrap items-center justify-between gap-4">
                    <form method="GET" action="{{ route('ward.ai-schedule') }}" class="flex items-center gap-2">
                        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                        <input type="hidden" name="tab" :value="tab">
                        <label for="ai_ward" class="text-sm font-semibold text-gray-700">Ward</label>
                        <select id="ai_ward" name="ward_id" onchange="this.form.submit()"
                            class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach ($wards as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $ward->id)>{{ $option->ward_name }}</option>
                            @endforeach
                        </select>
                    </form>

                    <div class="flex items-center gap-2">
                        <a href="{{ $weekLink($weekStart->copy()->subWeek()) }}" title="Previous week"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </a>
                        <div class="text-center min-w-[11rem]">
                            <div class="text-sm font-bold text-gray-800">{{ $weekStart->format('j M') }} &ndash; {{ $weekEnd->format('j M Y') }}</div>
                            <div class="text-[11px] text-gray-500">Week {{ $weekStart->isoWeek() }}</div>
                        </div>
                        <a href="{{ $weekLink($weekStart->copy()->addWeek()) }}" title="Next week"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </a>
                        @unless (now()->betweenIncluded($weekStart, $weekEnd->copy()->endOfDay()))
                            <a href="{{ $weekLink(now()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)) }}"
                                class="ml-1 text-sm font-semibold text-blue-600 hover:text-blue-800">This week</a>
                        @endunless
                    </div>

                    <p class="text-xs text-gray-500 max-w-sm">
                        {{ $board['team']->count() }} nurses on this roster:
                        @if ($board['teamSource'] === \App\Services\NurseScheduling\WardTeam::SOURCE_WARD)
                            those with {{ $ward->ward_name }} as their home ward.
                        @else
                            no nurse has {{ $ward->ward_name }} as a home ward yet, so every active nurse without one is listed.
                        @endif
                    </p>
                </div>

                {{-- Tabs --}}
                @php
                    $tabs = [
                        'roster' => 'Roster',
                        'assign' => 'Bed assignment',
                        'workload' => 'Workload',
                        'leave' => 'Leave & holidays',
                    ];
                    $requestsToDecide = ($rosterRequests ?? collect())->where('status', \App\Models\NurseRosterRequest::STATUS_PENDING)->count();
                @endphp
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex flex-wrap gap-x-6" aria-label="Tabs">
                        @foreach ($tabs as $key => $label)
                            <button type="button" @click="tab = '{{ $key }}'"
                                :class="tab === '{{ $key }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">
                                {{ $label }}
                                @if ($key === 'leave' && $requestsToDecide)
                                    <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white"
                                        title="{{ $requestsToDecide }} request(s) from the nurse app to decide">{{ $requestsToDecide }}</span>
                                @endif
                            </button>
                        @endforeach
                    </nav>
                </div>

                @include('wards.ai-schedule.partials.roster')
                @include('wards.ai-schedule.partials.assign')
                @include('wards.ai-schedule.partials.workload')
                @include('wards.ai-schedule.partials.leave')
            @endif
        </div>
    </div>
</x-app-layout>
