{{--
    Consultant Orders tab of the patient details. Open orders are listed in
    the order they were assigned, each with its urgency and the nurse the
    roster puts on the bed, and are passed on to the next shift from here.

    Expects $patient, and $consultantOrderTab when the tab button has
    already loaded it (ConsultantOrder::tabFor).
--}}

@php
    $orderTab = $consultantOrderTab ?? \App\Models\ConsultantOrder::tabFor($patient);
    $openOrders = $orderTab['open'];
    $closedOrders = $orderTab['closed'];
    $nowSlot = $orderTab['slots']['current'];
    $nextSlot = $orderTab['slots']['next'];
    $statCount = $openOrders->where('urgency', 'stat')->count();

    $urgencyStyles = [
        'stat' => [
            'badge' => 'bg-red-600 text-white',
            'border' => 'border-l-red-500',
            'pill' => 'peer-checked:bg-red-600 peer-checked:border-red-600 peer-checked:text-white',
        ],
        'urgent' => [
            'badge' => 'bg-amber-400 text-white',
            'border' => 'border-l-amber-400',
            'pill' => 'peer-checked:bg-amber-400 peer-checked:border-amber-400 peer-checked:text-white',
        ],
        'routine' => [
            'badge' => 'bg-gray-200 text-gray-700',
            'border' => 'border-l-gray-300',
            'pill' => 'peer-checked:bg-gray-700 peer-checked:border-gray-700 peer-checked:text-white',
        ],
    ];

    // Open orders not already with the next shift, for "Pass all"
    $passableIds = $nextSlot ? $openOrders->reject(fn ($order) => $order->isInSlot($nextSlot))->pluck('id') : collect();

    $patientConsultantIds = $orderTab['patientConsultantIds'];
    $patientConsultants = collect($patientConsultantIds)
        ->map(fn ($id) => $orderTab['consultants']->firstWhere('id', $id))
        ->filter();
    $otherConsultants = $orderTab['consultants']->whereNotIn('id', $patientConsultantIds);
    $selectedConsultant = (int) old('consultant_id', $orderTab['defaultConsultantId']);

    // Only errors from this tab's own forms, which post active_tab=orders
    $ownErrors = old('active_tab') === 'orders';
    $orderFormErrors = $ownErrors && $errors->hasAny(['instruction', 'urgency', 'consultant_id', 'ordered_at']);
    $orderErrors = $ownErrors
        ? collect(['instruction', 'urgency', 'consultant_id', 'ordered_at', 'outcome_note', 'order_ids', 'note', 'to'])
            ->flatMap(fn ($field) => $errors->get($field))
        : collect();

    $dayLabel = fn ($at) => $at->isToday() ? 'Today' : ($at->isYesterday() ? 'Yesterday' : ($at->isTomorrow() ? 'Tomorrow' : $at->format('j M')));
    $nurseInitials = fn ($name) => \Illuminate\Support\Str::upper(collect(preg_split('/\s+/', trim((string) $name)))
        ->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    $slotNurse = fn (?array $slot) => $slot && $slot['nurse'] ? $slot['nurse']->name : null;
@endphp

<div x-show="activeTab === 'orders'" x-cloak>
<div x-data="{
    adding: {{ $orderFormErrors || ($openOrders->isEmpty() && $closedOrders->isEmpty()) ? 'true' : 'false' }},
    passingAll: false,
    showClosed: false,
}">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                Consultant Orders
            </h3>
            @if ($openOrders->isNotEmpty())
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    {{ $openOrders->count() }} open
                </span>
            @endif
            @if ($statCount > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">
                    {{ $statCount }} STAT
                </span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($passableIds->isNotEmpty())
                <button type="button" @click="passingAll = !passingAll; adding = false"
                    class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-md shadow-sm border transition-colors"
                    :class="passingAll ? 'bg-gray-200 text-gray-700 border-gray-200 hover:bg-gray-300' : 'bg-white text-indigo-700 border-indigo-200 hover:bg-indigo-50'">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                    Pass all to {{ $nextSlot['label'] }}
                </button>
            @endif
            <button type="button" @click="adding = !adding; passingAll = false"
                class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-md shadow-sm transition-colors"
                :class="adding ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-blue-600 text-white hover:bg-blue-700'">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        :d="adding ? 'M6 18L18 6M6 6l12 12' : 'M12 4v16m8-8H4'" />
                </svg>
                <span x-text="adding ? 'Cancel' : 'New order'">New order</span>
            </button>
        </div>
    </div>

    @if ($orderErrors->isNotEmpty())
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach ($orderErrors as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    {{-- Who holds the orders now, and who takes them next --}}
    <div class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ([['On now', $nowSlot], ['Next shift', $nextSlot]] as [$heading, $slot])
            @php $rostered = $slotNurse($slot); @endphp
            <div class="flex items-center gap-3 rounded-lg border px-3 py-2 {{ $rostered ? 'border-gray-200 bg-white' : 'border-amber-200 bg-amber-50' }}">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $rostered ? 'bg-pink-500 text-white' : 'bg-amber-200 text-amber-800' }}">
                    {{ $rostered ? $nurseInitials($rostered) : '?' }}
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        {{ $heading }}
                        @if ($slot)
                            &middot; {{ $slot['label'] }} <span class="font-normal normal-case">{{ $slot['time'] }}</span>
                        @endif
                    </div>
                    <div class="truncate text-sm font-semibold {{ $rostered ? 'text-gray-800' : 'text-amber-800' }}">
                        {{ $slot ? ($rostered ?? 'No nurse rostered to this bed') : 'No shift set up for this time' }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- End of shift: pass everything on at once --}}
    @if ($passableIds->isNotEmpty())
        <div x-show="passingAll" x-cloak class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
            <form method="POST" action="{{ route('ward.consultant-orders.handover') }}">
                @csrf
                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                <input type="hidden" name="active_tab" value="orders">
                <input type="hidden" name="to" value="next">
                @foreach ($passableIds as $orderId)
                    <input type="hidden" name="order_ids[]" value="{{ $orderId }}">
                @endforeach
                <p class="text-sm text-gray-700">
                    Pass {{ $passableIds->count() }} open {{ \Illuminate\Support\Str::plural('order', $passableIds->count()) }} to
                    <span class="font-semibold">{{ $slotNurse($nextSlot) ?? 'the ' . $nextSlot['label'] . ' shift' }}</span>
                    ({{ $nextSlot['label'] }}, {{ $nextSlot['time'] }}).
                    @unless ($slotNurse($nextSlot))
                        <span class="text-amber-700">No nurse is rostered to this bed for it yet, so they will be unassigned.</span>
                    @endunless
                </p>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="co_pass_all_note" class="block text-xs font-semibold text-gray-700 mb-1">Handover note (optional)</label>
                        <input type="text" id="co_pass_all_note" name="note" maxlength="1000"
                            placeholder="Anything the next shift needs to know"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-indigo-700">
                        Pass {{ $passableIds->count() }} to {{ $nextSlot['label'] }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- New order --}}
    <div x-show="adding" x-cloak class="mb-4">
        <form method="POST" action="{{ route('ward.consultant-orders.store') }}"
            class="rounded-xl border border-blue-200 bg-blue-50/40 p-4">
            @csrf
            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
            <input type="hidden" name="active_tab" value="orders">

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="co_instruction" class="block text-xs font-semibold text-gray-700 mb-1">
                        Order <span class="text-red-500">*</span>
                    </label>
                    <textarea id="co_instruction" name="instruction" rows="3" maxlength="2000" required
                        placeholder="e.g. Repeat FBC and U&E at 06:00. Keep nil by mouth from midnight."
                        class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('instruction') }}</textarea>
                </div>

                <div class="sm:col-span-2">
                    <span class="block text-xs font-semibold text-gray-700 mb-1">Urgency</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Models\ConsultantOrder::URGENCIES as $key => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="urgency" value="{{ $key }}" class="peer sr-only"
                                    @checked(old('urgency', 'routine') === $key)>
                                <span class="inline-flex items-center rounded-full border border-gray-300 bg-white px-4 py-1.5 text-sm font-semibold text-gray-700 transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-blue-400 {{ $urgencyStyles[$key]['pill'] }}">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label for="co_consultant" class="block text-xs font-semibold text-gray-700 mb-1">
                        Consultant <span class="text-red-500">*</span>
                    </label>
                    <select id="co_consultant" name="consultant_id" required
                        class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select...</option>
                        @if ($patientConsultants->isNotEmpty())
                            <optgroup label="This patient's consultants">
                                @foreach ($patientConsultants as $consultant)
                                    <option value="{{ $consultant->id }}" @selected($selectedConsultant === $consultant->id)>{{ $consultant->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        <optgroup label="{{ $patientConsultants->isNotEmpty() ? 'Other consultants' : 'Consultants' }}">
                            @foreach ($otherConsultants as $consultant)
                                <option value="{{ $consultant->id }}" @selected($selectedConsultant === $consultant->id)>{{ $consultant->name }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <div>
                    <label for="co_ordered_at" class="block text-xs font-semibold text-gray-700 mb-1">Assigned at</label>
                    <input type="datetime-local" id="co_ordered_at" name="ordered_at"
                        value="{{ old('ordered_at', now()->format('Y-m-d\TH:i')) }}"
                        class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs {{ $slotNurse($nowSlot) ? 'text-gray-600' : 'text-amber-700' }}">
                    @if ($slotNurse($nowSlot))
                        Goes to <span class="font-semibold">{{ $slotNurse($nowSlot) }}</span>, rostered to this bed for the
                        {{ $nowSlot['label'] }} shift.
                    @else
                        No nurse is rostered to this bed for the shift on now, so the order stays unassigned until it is
                        passed on. Nurses are assigned in the roster.
                    @endif
                </p>
                <button type="submit"
                    class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700">
                    Save order
                </button>
            </div>
        </form>
    </div>

    {{-- Open orders, in the order they were assigned --}}
    @forelse ($openOrders as $order)
        @php
            $style = $urgencyStyles[$order->urgency] ?? $urgencyStyles['routine'];
            $inNext = $order->isInSlot($nextSlot);
            // Still with a shift that has ended: taken over by the shift on now rather than skipped past it
            $leftOver = $order->shift_code && !$order->isInSlot($nowSlot) && !$inNext;
            $passTo = $leftOver ? ['to' => 'current', 'slot' => $nowSlot] : ['to' => 'next', 'slot' => $nextSlot];
            $lastHandover = $order->handovers->first();
        @endphp
        <div x-data="{ panel: null }"
            class="mb-3 rounded-xl border border-gray-200 border-l-4 {{ $style['border'] }} bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="sm:w-20 sm:shrink-0">
                    <div class="text-lg font-bold leading-tight text-gray-800">{{ $order->ordered_at->format('H:i') }}</div>
                    <div class="text-xs text-gray-500">{{ $dayLabel($order->ordered_at) }}</div>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold {{ $style['badge'] }} {{ $order->urgency === 'stat' ? 'animate-pulse' : '' }}">
                            {{ $order->urgencyLabel() }}
                        </span>
                        <span class="text-xs text-gray-500">
                            {{ $order->consultant_name ?? 'Consultant not recorded' }}
                            @if ($order->createdBy)
                                &middot; entered by {{ $order->createdBy->name }}
                            @endif
                        </span>
                    </div>
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $order->instruction }}</p>

                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 {{ $order->assignedNurse ? 'border-pink-200 bg-pink-50 text-pink-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            {{ $order->assignedNurse->name ?? 'Unassigned' }}
                            @if ($order->shift_code)
                                <span class="font-semibold">&middot; {{ $order->slotLabel() }}</span>
                            @endif
                        </span>
                        @if ($inNext)
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 font-semibold text-indigo-800">Passed to next shift</span>
                        @elseif ($leftOver)
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">Shift ended, not passed on</span>
                        @endif
                        @if ($lastHandover)
                            <span class="text-gray-500">
                                From {{ $lastHandover->fromNurse->name ?? 'unassigned' }}@if ($lastHandover->fromLabel()) ({{ $lastHandover->fromLabel() }})@endif,
                                passed {{ $lastHandover->created_at->format('H:i') }}@if ($lastHandover->handedOverBy) by {{ $lastHandover->handedOverBy->name }}@endif
                                @if ($lastHandover->note)
                                    &middot; <span class="italic">"{{ $lastHandover->note }}"</span>
                                @endif
                                @if ($order->handovers->count() > 1)
                                    &middot; {{ $order->handovers->count() }} handovers
                                @endif
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 sm:w-36 sm:shrink-0 sm:flex-col sm:items-stretch">
                    <button type="button" @click="panel = panel === 'done' ? null : 'done'"
                        class="inline-flex items-center justify-center gap-1 rounded-md px-3 py-1.5 text-sm font-semibold shadow-sm transition-colors"
                        :class="panel === 'done' ? 'bg-emerald-700 text-white' : 'bg-emerald-600 text-white hover:bg-emerald-700'">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Done
                    </button>
                    @if ($passTo['slot'] && !$inNext)
                        <button type="button" @click="panel = panel === 'pass' ? null : 'pass'"
                            class="inline-flex items-center justify-center gap-1 rounded-md px-3 py-1.5 text-sm font-semibold shadow-sm transition-colors"
                            :class="panel === 'pass' ? 'bg-indigo-700 text-white' : 'bg-indigo-600 text-white hover:bg-indigo-700'">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                            {{ $leftOver ? 'Take over' : 'Pass to ' . $passTo['slot']['label'] }}
                        </button>
                    @endif
                    <button type="button" @click="panel = panel === 'cancel' ? null : 'cancel'"
                        class="inline-flex items-center justify-center rounded-md border px-3 py-1.5 text-sm font-semibold transition-colors"
                        :class="panel === 'cancel' ? 'border-gray-400 bg-gray-100 text-gray-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'">
                        Cancel order
                    </button>
                </div>
            </div>

            <div x-show="panel === 'done'" x-cloak class="mt-3 border-t border-gray-100 pt-3">
                <form method="POST" action="{{ route('ward.consultant-orders.complete', $order) }}"
                    class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    @csrf
                    <input type="hidden" name="active_tab" value="orders">
                    <div class="flex-1">
                        <label for="co_done_{{ $order->id }}" class="block text-xs font-semibold text-gray-700 mb-1">Note (optional)</label>
                        <input type="text" id="co_done_{{ $order->id }}" name="outcome_note" maxlength="1000"
                            placeholder="e.g. Bloods taken 10:20 and sent to the lab"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-emerald-700">
                        Mark done
                    </button>
                </form>
            </div>

            @if ($passTo['slot'] && !$inNext)
                <div x-show="panel === 'pass'" x-cloak class="mt-3 border-t border-gray-100 pt-3">
                    <form method="POST" action="{{ route('ward.consultant-orders.handover') }}"
                        class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        @csrf
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <input type="hidden" name="active_tab" value="orders">
                        <input type="hidden" name="to" value="{{ $passTo['to'] }}">
                        <input type="hidden" name="order_ids[]" value="{{ $order->id }}">
                        <div class="flex-1">
                            <label for="co_pass_{{ $order->id }}" class="block text-xs font-semibold text-gray-700 mb-1">Handover note (optional)</label>
                            <input type="text" id="co_pass_{{ $order->id }}" name="note" maxlength="1000"
                                placeholder="What the next nurse needs to know"
                                class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-indigo-700">
                            Pass to {{ $slotNurse($passTo['slot']) ?? 'the ' . $passTo['slot']['label'] . ' shift' }}
                        </button>
                    </form>
                    <p class="mt-1 text-xs {{ $slotNurse($passTo['slot']) ? 'text-gray-500' : 'text-amber-700' }}">
                        {{ $passTo['slot']['label'] }} shift, {{ $passTo['slot']['time'] }}.
                        @unless ($slotNurse($passTo['slot']))
                            No nurse is rostered to this bed for it yet, so the order will be unassigned.
                        @endunless
                    </p>
                </div>
            @endif

            <div x-show="panel === 'cancel'" x-cloak class="mt-3 border-t border-gray-100 pt-3">
                <form method="POST" action="{{ route('ward.consultant-orders.cancel', $order) }}"
                    class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    @csrf
                    <input type="hidden" name="active_tab" value="orders">
                    <div class="flex-1">
                        <label for="co_cancel_{{ $order->id }}" class="block text-xs font-semibold text-gray-700 mb-1">
                            Reason <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="co_cancel_{{ $order->id }}" name="outcome_note" maxlength="1000" required
                            placeholder="e.g. Discontinued by the consultant on the evening round"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2 bg-gray-700 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-gray-800">
                        Cancel order
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div class="p-6 text-center text-gray-500 border border-dashed border-gray-300 rounded-lg">
            <p class="text-sm">{{ $closedOrders->isEmpty() ? 'No consultant orders yet.' : 'No open orders.' }}</p>
        </div>
    @endforelse

    {{-- Done and cancelled --}}
    @if ($closedOrders->isNotEmpty())
        <div class="mt-5">
            <button type="button" @click="showClosed = !showClosed"
                class="inline-flex items-center text-sm font-semibold text-gray-600 hover:text-gray-800">
                <svg class="w-4 h-4 mr-1 transition-transform" :class="showClosed ? 'rotate-90' : ''" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                Done and cancelled ({{ $closedOrders->count() }})
            </button>
            <div x-show="showClosed" x-cloak class="mt-2 overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Assigned</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Order</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Outcome</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Closed</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach ($closedOrders->take(30) as $order)
                            <tr class="align-top">
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    {{ $order->ordered_at->format('d M H:i') }}
                                    <div>
                                        <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ ($urgencyStyles[$order->urgency] ?? $urgencyStyles['routine'])['badge'] }}">
                                            {{ $order->urgencyLabel() }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-gray-800">
                                    <p class="whitespace-pre-line">{{ $order->instruction }}</p>
                                    <div class="text-xs text-gray-500">
                                        {{ $order->consultant_name ?? 'Consultant not recorded' }}
                                        @if ($order->assignedNurse)
                                            &middot; {{ $order->assignedNurse->name }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $order->status === 'done' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-700' }}">
                                        {{ $order->status === 'done' ? 'Done' : 'Cancelled' }}
                                    </span>
                                    @if ($order->outcome_note)
                                        <div class="mt-1 text-xs text-gray-600">{{ $order->outcome_note }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                    {{ $order->closed_at?->format('d M H:i') }}
                                    @if ($order->closedBy)
                                        <div class="text-xs text-gray-500">{{ $order->closedBy->name }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($closedOrders->count() > 30)
                    <p class="px-3 py-2 text-xs text-gray-400 bg-gray-50">Showing the 30 most recent.</p>
                @endif
            </div>
        </div>
    @endif
</div>
</div>
