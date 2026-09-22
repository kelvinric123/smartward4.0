{{--
    Workload weights panel on the Bed assignment tab: how many points each
    factor adds to a bed's workload score, and how the AI shares beds out.
    Everyone can see the weights; only editors can change them.
--}}

@php
    $w = \App\Services\NurseScheduling\WorkloadWeights::class;
    $fmt = fn (float $value) => $w::format($value);
    $example = $weights->get('patient') + $weights->get('nursing_level_3') + $weights->get('ews_medium') + $weights->get('isolation');
@endphp

<div id="workload-weights" x-show="weightsOpen" x-cloak class="mt-4 rounded-xl border border-gray-200 bg-gray-50/70 scroll-mt-4">
    <form method="POST" action="{{ route('ward.ai-schedule.weights') }}">
        @csrf
        <input type="hidden" name="ward_id" value="{{ $ward->id }}">
        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
        <input type="hidden" name="day" value="{{ $day }}">
        <input type="hidden" name="shift" value="{{ $shift }}">
        @if ($suggesting)
            <input type="hidden" name="suggest" value="1">
        @endif

        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-4 py-3">
            <div>
                <h4 class="text-sm font-bold text-gray-800">Workload weights for {{ $ward->ward_name }}</h4>
                <p class="mt-0.5 max-w-3xl text-xs text-gray-500">
                    The points each factor adds to a bed's workload score. A nurse's load is the total over their beds,
                    and the AI shares beds out so those totals come out even. Set a weight to 0 to leave that factor out.
                </p>
            </div>
            @if ($weights->changedCount() > 0)
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                    {{ $weights->changedCount() }} changed from the defaults
                </span>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($w::GROUPS as $group => $fields)
                <fieldset class="rounded-lg border border-gray-200 bg-white p-3">
                    <legend class="px-1 text-xs font-bold uppercase tracking-wider text-gray-600">{{ $group }}</legend>
                    <div class="space-y-2.5">
                        @foreach ($fields as $key => $field)
                            @php
                                $percent = ($field['unit'] ?? null) === '%';
                                $changed = !$weights->isDefault($key);
                            @endphp
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <label for="weight_{{ $key }}" class="min-w-0">
                                        <span class="block text-sm font-medium text-gray-800">{{ $field['label'] }}</span>
                                        <span class="block text-[11px] leading-snug text-gray-500">
                                            {{ $field['hint'] }} &middot; default {{ $fmt($w::DEFAULTS[$key]) }}{{ $percent ? '%' : '' }}
                                        </span>
                                    </label>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <input type="number" id="weight_{{ $key }}" name="weights[{{ $key }}]"
                                            value="{{ old('weights.' . $key, $fmt($weights->get($key))) }}"
                                            min="0" max="{{ $w::max($key) }}" step="{{ $percent ? 5 : 0.1 }}" required
                                            @disabled(!$canEdit)
                                            class="w-20 rounded-md text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600
                                                {{ $errors->has('weights.' . $key) ? 'border-red-400' : ($changed ? 'border-amber-300 bg-amber-50' : 'border-gray-300') }}">
                                        <span class="w-6 text-xs text-gray-500">{{ $percent ? '%' : 'pts' }}</span>
                                    </div>
                                </div>
                                @error('weights.' . $key)
                                    <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>

        <p class="px-4 text-xs text-gray-600">
            <span class="font-semibold">Example:</span> a nursing level 3 patient with an EWS of 5, in isolation, scores
            {{ $fmt($weights->get('patient')) }} + {{ $fmt($weights->get('nursing_level_3')) }} + {{ $fmt($weights->get('ews_medium')) }}
            + {{ $fmt($weights->get('isolation')) }} = <span class="font-semibold">{{ $fmt($example) }}</span> points.
        </p>

        @if ($canEdit)
            <div class="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 px-4 py-3">
                <button type="submit" form="reset-workload-weights"
                    onclick="return confirm('Put every workload weight back to its default?')"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-gray-50">
                    Reset to defaults
                </button>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
                    Save weights
                </button>
            </div>
        @else
            <p class="mt-3 border-t border-gray-200 px-4 py-3 text-xs text-gray-500">Nurse managers and admins can change the weights.</p>
        @endif
    </form>

    @if ($canEdit)
        <form id="reset-workload-weights" method="POST" action="{{ route('ward.ai-schedule.weights.reset') }}" class="hidden">
            @csrf
            <input type="hidden" name="ward_id" value="{{ $ward->id }}">
            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
            <input type="hidden" name="day" value="{{ $day }}">
            <input type="hidden" name="shift" value="{{ $shift }}">
            @if ($suggesting)
                <input type="hidden" name="suggest" value="1">
            @endif
        </form>
    @endif
</div>
