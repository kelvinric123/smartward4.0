{{--
    Patient classification on the Patient acuity tab: every bed, the indicators
    behind each patient's suggested care level, the current rating, and a new
    rating to save. Not limited to editors: nurses rate the patients they care
    for, as Patient details already lets anyone signed in set the nursing level.
--}}

@php
    $occupied = $acuity->filter(fn (array $row) => $row['patient']);
    // Cast to objects so the patient ids stay keys in JavaScript, never array indexes
    $ratingState = [
        'levels' => (object) $occupied->mapWithKeys(fn (array $row) => [$row['patient']->id => (string) old('levels.' . $row['patient']->id, '')])->all(),
        'suggested' => (object) $occupied->mapWithKeys(fn (array $row) => [$row['patient']->id => $row['suggested']])->all(),
        'current' => (object) $occupied->mapWithKeys(fn (array $row) => [$row['patient']->id => $row['classified']])->all(),
    ];
    $dimensions = \App\Services\NurseScheduling\PatientAcuity::DIMENSIONS;
    $statusBadge = fn (array $row) => match ($row['status']) {
        'agrees' => ['bg-emerald-100 text-emerald-800', 'Agrees', 'The rating and the indicators agree'],
        'review_up' => ['bg-amber-100 text-amber-800', 'Review: indicators suggest Level ' . $row['suggested'], 'Rated below what the indicators suggest: check the rating, or note why it stands'],
        'review_down' => ['bg-sky-100 text-sky-800', 'Rated above indicators', 'Rated above what the indicators suggest, often for needs they cannot see'],
        default => ['bg-gray-100 text-gray-600', 'Not classified', 'No nurse has rated this patient yet; staffing counts the suggested level'],
    };
    $levelChip = fn (int $level) => '<span class="inline-flex items-center whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-semibold ' . $levelTone[$level] . '" title="' . e($levels[$level]['name']) . '">'
        . $level . ' ' . e($levels[$level]['short']) . '</span>';
@endphp

<form method="POST" action="{{ route('ward.ai-schedule.acuity') }}" class="bg-white rounded-2xl shadow-lg border border-blue-100"
    x-data="{
        levels: @js($ratingState['levels']),
        suggested: @js($ratingState['suggested']),
        current: @js($ratingState['current']),
        fillSuggested() {
            for (const id in this.levels) {
                if (this.current[id] === null && this.levels[id] === '') this.levels[id] = String(this.suggested[id]);
            }
        },
        confirmCurrent() {
            for (const id in this.levels) this.levels[id] = String(this.current[id] ?? this.suggested[id]);
        },
        differs(id) { return this.levels[id] !== '' && Number(this.levels[id]) !== this.suggested[id]; },
        get chosen() { return Object.values(this.levels).filter((level) => level !== '').length; },
    }">
    @csrf
    <input type="hidden" name="ward_id" value="{{ $ward->id }}">
    <input type="hidden" name="week" value="{{ $weekValue }}">

    <div class="p-5 border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Patient classification</h3>
            <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">
                Rate each patient against the care level descriptions. The suggestion comes from the indicators recorded in
                SmartWard; where your rating differs, say why. Saving keeps each rating with its indicators and sets the
                nursing level the ward dashboard shows. Every nurse can rate.
            </p>
        </div>
        @if ($occupied->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="fillSuggested()"
                    class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Fill suggested where not classified
                    @if ($summary['unclassified'])
                        <span class="ml-1.5 inline-flex items-center rounded-full bg-gray-100 px-1.5 text-[11px] font-bold text-gray-700">{{ $summary['unclassified'] }}</span>
                    @endif
                </button>
                <button type="button" @click="confirmCurrent()" title="Set every patient to their current rating, or the suggestion where there is none"
                    class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Confirm current ratings
                </button>
                <button type="submit" :disabled="chosen === 0"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    Save ratings
                    <span x-show="chosen" x-cloak class="ml-1.5 inline-flex items-center rounded-full bg-white px-1.5 text-[11px] font-bold text-indigo-700" x-text="chosen"></span>
                </button>
            </div>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gradient-to-r from-brand-50 to-accent-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Bed</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Patient</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Indicators</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Suggested</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Current rating</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider w-56">New rating</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($acuity as $row)
                    @php $patient = $row['patient']; @endphp
                    @if (!$patient)
                        <tr class="bg-gray-50/70">
                            <td class="px-4 py-2 whitespace-nowrap font-semibold text-gray-400">{{ $row['bed']->bed_display_name ?: $row['bed']->bed_number }}</td>
                            <td colspan="6" class="px-4 py-2 text-gray-400">Empty</td>
                        </tr>
                        @continue
                    @endif
                    @php
                        $id = $patient->id;
                        $rating = $row['last_rating'];
                        [$badgeTone, $badgeText, $badgeTitle] = $statusBadge($row);
                    @endphp
                    <tr class="align-top" :class="levels[{{ $id }}] !== '' ? 'bg-indigo-50/60' : ''">
                        <td class="px-4 py-2 whitespace-nowrap font-semibold text-gray-800">{{ $row['bed']->bed_display_name ?: $row['bed']->bed_number }}</td>
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-800">{{ $patient->name }}</div>
                            <div class="text-xs text-gray-500">{{ $patient->mrn }}</div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex max-w-xs flex-wrap gap-1">
                                @forelse ($row['reasons'] as $reason)
                                    <span class="inline-flex items-center rounded border px-1.5 py-0.5 text-[11px] font-medium {{ $levelTone[$reason['level']] }}"
                                        title="{{ $dimensions[$reason['dimension']] ?? 'Indicator' }}: suggests Level {{ $reason['level'] }}">{{ $reason['label'] }}</span>
                                @empty
                                    <span class="text-xs text-gray-400">No indicators</span>
                                @endforelse
                                @foreach ($row['notes'] as $note)
                                    <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-[11px] italic text-gray-600">{{ $note }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-2">{!! $levelChip($row['suggested']) !!}</td>
                        <td class="px-4 py-2">
                            @if ($row['classified'])
                                {!! $levelChip($row['classified']) !!}
                            @else
                                <span class="text-xs text-gray-500">Not classified</span>
                            @endif
                            @if ($rating)
                                <div class="mt-1 text-[11px] text-gray-500" title="{{ $rating->rated_at->format('d M Y H:i') }}{{ $rating->note ? ': ' . $rating->note : '' }}">
                                    {{ $rating->level !== $row['classified'] ? 'Last rated Level ' . $rating->level . ',' : 'Rated' }}
                                    {{ $rating->rated_at->diffForHumans() }} by {{ $rating->rated_by_name ?: 'unknown' }}
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $badgeTone }}" title="{{ $badgeTitle }}">{{ $badgeText }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <select name="levels[{{ $id }}]" x-model="levels[{{ $id }}]" aria-label="New rating for {{ $patient->name }}"
                                :class="levels[{{ $id }}] !== '' ? 'border-indigo-400 ring-1 ring-indigo-300' : 'border-gray-300'"
                                class="block w-full rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">&mdash; no change &mdash;</option>
                                @foreach ($levels as $level => $info)
                                    <option value="{{ $level }}">{{ $level }} {{ $info['short'] }}{{ $level === $row['suggested'] ? ' (suggested)' : '' }}</option>
                                @endforeach
                            </select>
                            {{-- Disabled while hidden, so a note left from another choice is not saved --}}
                            <input type="text" name="notes[{{ $id }}]" maxlength="255" value="{{ old('notes.' . $id) }}"
                                placeholder="Why it differs from the suggestion" aria-label="Why the rating for {{ $patient->name }} differs from the suggestion"
                                x-show="differs({{ $id }})" x-cloak :disabled="!differs({{ $id }})"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500">
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-gray-500">No beds on {{ $ward->ward_name }} yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($occupied->isNotEmpty())
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4">
            <p class="text-xs text-gray-500">
                <span x-text="chosen">0</span> to save. Choosing the level a patient already has confirms it; the rating check
                below compares every rating with the indicators.
            </p>
            <button type="submit" :disabled="chosen === 0"
                class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Save ratings
            </button>
        </div>
    @endif
</form>
