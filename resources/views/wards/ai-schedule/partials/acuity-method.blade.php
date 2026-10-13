{{--
    How the care levels are suggested and how acuity becomes nurses per shift: the
    levels, every indicator with the level it suggests and its data, and the sums.
    Shown on the Patient acuity tab, in the acuity printout and on the shared view,
    so it must not depend on the signed-in user or link into the app.

    Needs: $acuitySettings (AcuitySettings), $shifts (WardShifts::forWard).
    Optional: $compact (bool) for print.
--}}
@php
    $compact = $compact ?? false;
    $levelTone = [
        1 => 'bg-green-100 text-green-800 border-green-300',
        2 => 'bg-blue-100 text-blue-800 border-blue-300',
        3 => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        4 => 'bg-red-100 text-red-800 border-red-300',
    ];
    $criteria = collect(\App\Services\NurseScheduling\PatientAcuity::CRITERIA)->groupBy('dimension');
    $fmt = fn ($value) => \App\Services\NurseScheduling\AcuitySettings::format((float) $value);
@endphp

<div class="acuity-method space-y-4 {{ $compact ? 'text-[11px]' : 'text-sm' }} text-gray-700">
    <div>
        <h4 class="font-semibold text-gray-800 {{ $compact ? 'text-xs' : 'text-sm' }}">Care levels</h4>
        <p class="text-xs text-gray-500 mt-0.5">The nurse rates each patient against these descriptions (a prototype classification). The level is the one the ward dashboard shows.</p>
        {{-- Wider than a phone: the table scrolls sideways inside its card --}}
        <div class="mt-2 overflow-x-auto">
            <table class="w-full border-collapse">
                <tbody>
                    @foreach (\App\Services\NurseScheduling\PatientAcuity::LEVELS as $level => $info)
                        <tr class="border-t border-gray-200 align-top">
                            <td class="py-1.5 pr-3 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $levelTone[$level] }}">Level {{ $level }} {{ $info['short'] }}</span>
                            </td>
                            <td class="py-1.5 pr-3">{{ $info['description'] }}</td>
                            <td class="py-1.5 whitespace-nowrap text-right text-xs text-gray-500">{{ $fmt($acuitySettings->hours($level)) }} h of care a day</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <h4 class="font-semibold text-gray-800 {{ $compact ? 'text-xs' : 'text-sm' }}">How the levels are suggested</h4>
        <p class="text-xs text-gray-500 mt-0.5">
            Each indicator a patient meets suggests a lowest level. The suggestion is the highest of them, one level higher when
            {{ \App\Services\NurseScheduling\PatientAcuity::COMPLEX_AREAS }} or more areas of care ask for that level at once.
            A rating that differs from the suggestion is flagged for review; the nurse's rating stands.
            Early warning scores are worked out with {{ $acuitySettings->ewsSystemName() }}, the ward's setting.
        </p>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-gray-500">
                        <th class="py-1 pr-3 font-semibold">Area of care</th>
                        <th class="py-1 pr-3 font-semibold">Indicator</th>
                        <th class="py-1 pr-3 font-semibold">Suggests</th>
                        <th class="py-1 font-semibold">Read from</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($criteria as $dimension => $items)
                        @foreach ($items as $item)
                            <tr class="border-t border-gray-200 align-top">
                                <td class="py-1 pr-3 font-medium text-gray-800">{{ $loop->first ? \App\Services\NurseScheduling\PatientAcuity::DIMENSIONS[$dimension] : '' }}</td>
                                <td class="py-1 pr-3">{{ $item['indicator'] }}</td>
                                <td class="py-1 pr-3 whitespace-nowrap">
                                    @if ($item['level'])
                                        <span class="inline-flex items-center rounded-full border px-1.5 py-0.5 text-[11px] font-semibold {{ $levelTone[$item['level']] }}">Level {{ $item['level'] }}</span>
                                    @else
                                        <span class="text-xs text-gray-500">One level up</span>
                                    @endif
                                </td>
                                <td class="py-1 text-xs text-gray-500">{{ $item['source'] }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <h4 class="font-semibold text-gray-800 {{ $compact ? 'text-xs' : 'text-sm' }}">From care levels to nurses per shift</h4>
        <ol class="mt-1 list-inside space-y-0.5" style="list-style-type: decimal">
            <li>Care hours in 24 h: each patient's hours for their level, added up (an unclassified patient counts at the suggested level).</li>
            <li>Care hours on a shift: the day's hours times the shift's share
                (@foreach (\App\Services\NurseScheduling\WardShifts::CODES as $code){{ $code }} {{ $fmt($acuitySettings->share($code)) }}%{{ $loop->last ? '' : ', ' }}@endforeach).</li>
            <li>Nurses on the shift: the shift's care hours divided by its length
                (@foreach (\App\Services\NurseScheduling\WardShifts::CODES as $code){{ $code }} {{ $fmt($shifts[$code]['hours'] ?? 0) }} h{{ $loop->last ? '' : ', ' }}@endforeach),
                rounded up to whole nurses, at least one while there are patients.</li>
            <li>Only registered nurses count against it. The minimum in the staffing rules stays the floor.</li>
        </ol>
    </div>

    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ \App\Support\AcuityReferences::DISCLAIMER }}</p>
</div>
