{{-- Clinical alerts from the patient record. The record keeps only the current
     value of each (no history), so the heading says so rather than implying
     they held for the whole stay. --}}
@php
    $ca = $clinicalAlerts;
    $activeAllergies = $ca['allergies']->reject(fn($allergy) => $allergy['resolved']);
@endphp

<section data-section="alerts" class="mb-5 {{ $sectionBox }}">
    <h3 class="{{ $sectionHeading }}">
        <span>Clinical alerts</span>
        <span class="{{ $sectionCount }}">as currently recorded on the patient record</span>
    </h3>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 px-4 py-3 sm:grid-cols-4">
        <div class="col-span-2">
            <x-summary-field label="Allergies">
                @if ($ca['allergies']->isEmpty())
                    <span class="text-gray-600">No known allergies recorded</span>
                @else
                    <span class="flex flex-wrap gap-1">
                        @foreach ($ca['allergies'] as $allergy)
                            <span
                                class="{{ $allergy['resolved'] ? 'border-gray-200 bg-gray-50 text-gray-400 line-through' : 'border-red-200 bg-red-50 font-semibold text-red-700' }} inline-flex items-center rounded border px-1.5 py-0.5 text-xs"
                                title="{{ $allergy['resolved'] ? 'Resolved' : 'Active' }}">
                                {{ $allergy['name'] }}
                            </span>
                        @endforeach
                    </span>
                    @if ($activeAllergies->isEmpty())
                        <span class="mt-1 block text-xs text-gray-500">All recorded allergies are marked resolved.</span>
                    @endif
                @endif
            </x-summary-field>
        </div>

        <div class="col-span-2">
            <x-summary-field label="Diet">
                @if ($ca['nbm'] || $ca['diet'] || $ca['dietOrders'] || $ca['feeding'])
                    @if ($ca['nbm'])
                        <span class="mr-1 inline-flex rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">Nil by mouth</span>
                    @endif
                    {{ $ca['diet'] }}
                    @if ($ca['feeding'])
                        <span class="block text-xs text-gray-600">Feeding: {{ $ca['feeding'] }}</span>
                    @endif
                    @if ($ca['dietOrders'])
                        <span class="block whitespace-pre-line text-xs text-gray-600">{{ $ca['dietOrders'] }}</span>
                    @endif
                @endif
            </x-summary-field>
        </div>

        <x-summary-field label="Fall risk">
            @if ($ca['fallRisk'])
                <span class="{{ in_array($ca['fallRisk'], ['High', 'FR Alert Active'], true) ? 'font-semibold text-red-700' : '' }}">
                    {{ $ca['fallRisk'] }}
                </span>
            @endif
        </x-summary-field>
        <x-summary-field label="Isolation">
            @if ($ca['isolation'])
                <span class="font-semibold text-purple-700">{{ $ca['isolation'] }}</span>
            @endif
        </x-summary-field>
        <x-summary-field label="Level of care" :value="$ca['nursingLevel']" />
        <x-summary-field label="HGT monitoring" :value="$ca['hgt']" />
    </dl>
</section>
