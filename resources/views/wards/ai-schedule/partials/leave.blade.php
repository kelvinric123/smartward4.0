{{-- Leave & holidays tab: requests from the nurse app, nurse leave (which the roster never books over), and public holidays --}}

@php
    $weekValue = $weekStart->toDateString();
    $leaveStyle = [
        'medical' => 'bg-rose-100 text-rose-800',
        'emergency' => 'bg-rose-100 text-rose-800',
        'training' => 'bg-violet-100 text-violet-800',
        'off_request' => 'bg-gray-100 text-gray-700',
    ];
@endphp

<div x-show="tab === 'leave'" x-cloak class="grid grid-cols-1 gap-4 xl:grid-cols-5">
    {{-- Requests from the nurse app: leave, and swaps a colleague has accepted --}}
    @php
        $requestStyle = [
            'pending' => 'bg-amber-100 text-amber-800',
            'awaiting_colleague' => 'bg-sky-100 text-sky-800',
            'approved' => 'bg-emerald-100 text-emerald-800',
            'declined' => 'bg-rose-100 text-rose-800',
            'cancelled' => 'bg-gray-100 text-gray-600',
        ];
        $openRequests = $rosterRequests->filter->isOpen();
        $decidedRequests = $rosterRequests->reject->isOpen();
    @endphp
    <div class="xl:col-span-5 bg-white rounded-2xl shadow-lg border border-blue-100" id="roster-requests">
        <div class="p-5 border-b border-gray-100 flex flex-wrap items-start justify-between gap-2">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Requests from the nurse app</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Leave asked for in the nurse app, and shift swaps once the colleague has accepted. Approving leave books it as above;
                    approving a swap swaps the two nurses' shifts that day, in the roster and in the beds they hold.
                </p>
            </div>
            @if ($openRequests->where('status', 'pending')->count())
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500 text-white">{{ $openRequests->where('status', 'pending')->count() }} to decide</span>
            @endif
        </div>

        @if ($rosterRequests->isEmpty())
            <p class="p-5 text-sm text-gray-500">No requests from the nurse app for {{ $ward->ward_name }}.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($openRequests->concat($decidedRequests) as $req)
                    <li class="p-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between {{ $req->isOpen() ? '' : 'opacity-70' }}">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-800">{{ $req->nurse?->name ?? 'Nurse' }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase tracking-wide {{ $req->isSwap() ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800' }}">{{ $req->isSwap() ? 'Swap' : 'Leave' }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $requestStyle[$req->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $req->statusLabel() }}</span>
                            </div>
                            <div class="mt-1 text-sm text-gray-700">{{ $req->summary() }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">
                                Asked {{ $req->created_at?->format('d M H:i') }}
                                @if ($req->note) &middot; &ldquo;{{ $req->note }}&rdquo; @endif
                                @if ($req->isSwap() && $req->colleague_responded_at && $req->status !== 'declined')
                                    &middot; accepted by {{ $req->colleague?->name }} {{ $req->colleague_responded_at->format('d M H:i') }}
                                @endif
                                @if ($req->decided_at)
                                    &middot; {{ strtolower($req->statusLabel()) }} {{ $req->decided_at->format('d M H:i') }}{{ $req->decided_by_name ? ' by ' . $req->decided_by_name : '' }}{{ $req->decision_note ? ': ' . $req->decision_note : '' }}
                                @endif
                            </div>
                        </div>

                        @if ($canEdit && $req->isOpen())
                            <div class="flex flex-wrap items-center gap-2 shrink-0" x-data="{ note: '' }">
                                <input type="text" x-model="note" maxlength="255" placeholder="Note to the nurse (optional)"
                                    class="w-56 rounded-lg border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                @if ($req->status === 'pending')
                                    <form method="POST" action="{{ route('ward.ai-schedule.requests.approve', $req) }}">
                                        @csrf
                                        <input type="hidden" name="week" value="{{ $weekValue }}">
                                        <input type="hidden" name="decision_note" :value="note">
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">Approve</button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-500">Waiting for {{ $req->colleague?->name ?? 'the colleague' }}</span>
                                @endif
                                <form method="POST" action="{{ route('ward.ai-schedule.requests.decline', $req) }}">
                                    @csrf
                                    <input type="hidden" name="week" value="{{ $weekValue }}">
                                    <input type="hidden" name="decision_note" :value="note">
                                    <button type="submit" class="px-3 py-1.5 rounded-lg border border-rose-300 text-rose-700 text-xs font-semibold hover:bg-rose-50">Decline</button>
                                </form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Leave --}}
    <div class="xl:col-span-3 bg-white rounded-2xl shadow-lg border border-blue-100">
        <div class="p-5 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">Leave</h3>
            <p class="text-xs text-gray-500 mt-0.5">The roster never books a nurse on a leave day. Adding leave removes any AI-planned shifts on those days.</p>
        </div>

        @if ($canEdit)
            <form method="POST" action="{{ route('ward.ai-schedule.leaves.store') }}" class="p-5 border-b border-gray-100 bg-gray-50/60">
                @csrf
                <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                <input type="hidden" name="week" value="{{ $weekValue }}">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="lg:col-span-2">
                        <label for="leave_nurse" class="block text-xs font-semibold text-gray-700 mb-1">Nurse <span class="text-red-500">*</span></label>
                        <select id="leave_nurse" name="nurse_id" required
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select...</option>
                            @foreach ($board['team'] as $nurse)
                                <option value="{{ $nurse->id }}" @selected((int) old('nurse_id') === $nurse->id)>{{ $nurse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="leave_type" class="block text-xs font-semibold text-gray-700 mb-1">Type</label>
                        <select id="leave_type" name="type"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (\App\Models\NurseLeave::TYPES as $key => $type)
                                <option value="{{ $key }}" @selected(old('type', 'annual') === $key)>{{ $type['label'] }} ({{ $type['code'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="leave_start" class="block text-xs font-semibold text-gray-700 mb-1">From <span class="text-red-500">*</span></label>
                        <input type="date" id="leave_start" name="start_date" required value="{{ old('start_date', $weekValue) }}"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="leave_end" class="block text-xs font-semibold text-gray-700 mb-1">To <span class="text-red-500">*</span></label>
                        <input type="date" id="leave_end" name="end_date" required value="{{ old('end_date', $weekValue) }}"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-5">
                        <label for="leave_note" class="block text-xs font-semibold text-gray-700 mb-1">Note (optional)</label>
                        <input type="text" id="leave_note" name="note" maxlength="255" value="{{ old('note') }}"
                            placeholder="e.g. Approved by the nurse manager"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
                            Add leave
                        </button>
                    </div>
                </div>
            </form>
        @endif

        <div class="divide-y divide-gray-100">
            @forelse ($leaves as $leave)
                @php $past = $leave->end_date->lt(now()->startOfDay()); @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 {{ $past ? 'opacity-60' : '' }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="inline-flex items-center justify-center w-10 rounded-md py-1 text-xs font-bold {{ $leaveStyle[$leave->type] ?? 'bg-emerald-100 text-emerald-800' }}">{{ $leave->code() }}</span>
                        <div class="min-w-0">
                            <div class="font-medium text-gray-800">{{ $leave->nurse->name ?? 'Nurse' }}
                                <span class="font-normal text-gray-500">&middot; {{ $leave->label() }}</span></div>
                            <div class="text-xs text-gray-500">
                                {{ $leave->start_date->format('D j M') }}@if (!$leave->start_date->equalTo($leave->end_date)) &ndash; {{ $leave->end_date->format('D j M Y') }}@else {{ $leave->start_date->format('Y') }}@endif
                                &middot; {{ $leave->days() }} {{ \Illuminate\Support\Str::plural('day', $leave->days()) }}
                                @if ($leave->note)
                                    &middot; {{ $leave->note }}
                                @endif
                            </div>
                        </div>
                    </div>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('ward.ai-schedule.leaves.destroy', $leave) }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                            <input type="hidden" name="week" value="{{ $weekValue }}">
                            <button type="button" onclick="confirmDelete(event, 'Remove this leave? The roster may book the nurse on those days again.')"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200" title="Remove leave">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                <span class="sr-only">Remove</span>
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-gray-500">No leave booked from last week onwards.</p>
            @endforelse
        </div>
    </div>

    {{-- Public holidays --}}
    <div class="xl:col-span-2 bg-white rounded-2xl shadow-lg border border-blue-100">
        <div class="p-5 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">Public holidays</h3>
            <p class="text-xs text-gray-500 mt-0.5">Marked on the roster. AI shares holiday shifts out evenly, so the same nurses are not always on.</p>
        </div>

        @if ($canEdit)
            <form method="POST" action="{{ route('ward.ai-schedule.holidays.store') }}" class="p-5 border-b border-gray-100 bg-gray-50/60">
                @csrf
                <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                <input type="hidden" name="week" value="{{ $weekValue }}">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
                    <div class="sm:col-span-2">
                        <label for="holiday_date" class="block text-xs font-semibold text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" id="holiday_date" name="holiday_date" required value="{{ old('holiday_date') }}"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="holiday_name" class="block text-xs font-semibold text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" id="holiday_name" name="name" required maxlength="100" value="{{ old('name') }}"
                            placeholder="e.g. Malaysia Day"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="sm:col-span-5 flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
                            Add holiday
                        </button>
                    </div>
                </div>
            </form>
        @endif

        <div class="divide-y divide-gray-100">
            @forelse ($holidays as $holiday)
                @php $past = $holiday->holiday_date->lt(now()->startOfDay()); @endphp
                <div class="flex items-center justify-between gap-3 px-5 py-3 {{ $past ? 'opacity-60' : '' }}">
                    <div>
                        <div class="font-medium text-gray-800">{{ $holiday->name }}</div>
                        <div class="text-xs text-gray-500">{{ $holiday->holiday_date->format('l j M Y') }}</div>
                    </div>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('ward.ai-schedule.holidays.destroy', $holiday) }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                            <input type="hidden" name="week" value="{{ $weekValue }}">
                            <button type="button" onclick="confirmDelete(event, 'Remove this public holiday?')"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200" title="Remove holiday">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                <span class="sr-only">Remove</span>
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-gray-500">No public holidays added for this year yet.</p>
            @endforelse
        </div>
    </div>
</div>
