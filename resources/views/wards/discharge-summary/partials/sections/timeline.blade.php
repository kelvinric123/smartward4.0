{{-- Clinical timeline: every recorded event of the stay, day by day, built by
     App\Support\AdmissionTimeline. The event-type chips and the order toggle
     are screen-only; what they hide stays hidden on paper, and the printout
     says so. Colour classes are spelled out here so Tailwind can see them. --}}
@php
    $timelineColors = [
        'admission' => ['dot' => 'bg-blue-500', 'text' => 'text-blue-700', 'chip' => 'border-blue-200 bg-blue-50 text-blue-800'],
        'care' => ['dot' => 'bg-indigo-500', 'text' => 'text-indigo-700', 'chip' => 'border-indigo-200 bg-indigo-50 text-indigo-800'],
        'vitals' => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'chip' => 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        'fluid' => ['dot' => 'bg-cyan-500', 'text' => 'text-cyan-700', 'chip' => 'border-cyan-200 bg-cyan-50 text-cyan-800'],
        'medication' => ['dot' => 'bg-violet-500', 'text' => 'text-violet-700', 'chip' => 'border-violet-200 bg-violet-50 text-violet-800'],
        'infusion' => ['dot' => 'bg-sky-500', 'text' => 'text-sky-700', 'chip' => 'border-sky-200 bg-sky-50 text-sky-800'],
        'transfusion' => ['dot' => 'bg-rose-500', 'text' => 'text-rose-700', 'chip' => 'border-rose-200 bg-rose-50 text-rose-800'],
        'order' => ['dot' => 'bg-amber-500', 'text' => 'text-amber-700', 'chip' => 'border-amber-200 bg-amber-50 text-amber-800'],
        'assessment' => ['dot' => 'bg-fuchsia-500', 'text' => 'text-fuchsia-700', 'chip' => 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-800'],
        'ecg' => ['dot' => 'bg-pink-500', 'text' => 'text-pink-700', 'chip' => 'border-pink-200 bg-pink-50 text-pink-800'],
        'movement' => ['dot' => 'bg-orange-500', 'text' => 'text-orange-700', 'chip' => 'border-orange-200 bg-orange-50 text-orange-800'],
        'alert' => ['dot' => 'bg-red-500', 'text' => 'text-red-700', 'chip' => 'border-red-200 bg-red-50 text-red-800'],
    ];
    $shortLabels = [
        'admission' => 'Admission',
        'care' => 'Doctors',
        'vitals' => 'Vitals',
        'fluid' => 'I/O',
        'medication' => 'Medication',
        'infusion' => 'Infusion',
        'transfusion' => 'Transfusion',
        'order' => 'Order',
        'assessment' => 'Assessment',
        'ecg' => 'ECG',
        'movement' => 'Movement',
        'alert' => 'Alert',
    ];
    $toneTitle = [
        'default' => 'text-gray-900',
        'good' => 'text-green-700',
        'warning' => 'text-amber-700',
        'critical' => 'text-red-700',
        'muted' => 'text-gray-500',
    ];
    $toneBadge = [
        'default' => 'bg-gray-100 text-gray-700',
        'good' => 'bg-green-100 text-green-800',
        'warning' => 'bg-amber-100 text-amber-800',
        'critical' => 'bg-red-100 text-red-800',
        'muted' => 'bg-gray-100 text-gray-600',
    ];
    $usedCategories = collect($timeline['categories'])->filter(fn($category) => $category['count'] > 0);
@endphp

<section data-section="timeline" class="mb-5 rounded-lg border border-gray-300 bg-white"
    x-data="{
        hidden: [],
        newestFirst: false,
        shown(key) { return !this.hidden.includes(key); },
        toggle(key) {
            this.hidden = this.shown(key) ? this.hidden.concat(key) : this.hidden.filter((k) => k !== key);
        },
        anyShown(keys) { return keys.some((key) => this.shown(key)); },
    }">
    <h3 class="{{ $sectionHeading }}">
        <span>Clinical timeline</span>
        <span class="{{ $sectionCount }}">
            {{ number_format($timeline['total']) }} {{ Str::plural('event', $timeline['total']) }}, day by day
        </span>
    </h3>

    @if ($timeline['total'] === 0)
        <p class="px-4 py-3 text-sm text-gray-500">Nothing has been recorded for this admission yet.</p>
    @else
        <div class="flex flex-wrap items-center gap-1.5 border-b border-gray-200 px-4 py-2 print:hidden">
            <span class="mr-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500">Show</span>
            @foreach ($usedCategories as $key => $category)
                <button type="button" @click="toggle('{{ $key }}')"
                    :class="shown('{{ $key }}') ? '{{ $timelineColors[$key]['chip'] }}' : 'border-gray-200 bg-white text-gray-400 line-through'"
                    :aria-pressed="shown('{{ $key }}').toString()"
                    class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium transition-colors">
                    <span class="h-2 w-2 rounded-full {{ $timelineColors[$key]['dot'] }}"></span>
                    {{ $category['label'] }}
                    <span class="font-semibold">{{ $category['count'] }}</span>
                </button>
            @endforeach

            <span class="ml-auto flex items-center gap-3">
                <button type="button" x-show="hidden.length" x-cloak @click="hidden = []"
                    class="text-xs font-semibold text-blue-600 hover:text-blue-800">Show all</button>
                <button type="button" @click="newestFirst = !newestFirst"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2 py-0.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                    </svg>
                    <span x-text="newestFirst ? 'Newest first' : 'Oldest first'">Oldest first</span>
                </button>
            </span>
        </div>

        <p x-show="hidden.length" x-cloak class="hidden border-b border-gray-200 px-4 py-1.5 text-[11px] font-semibold text-gray-700 print:block">
            Filtered copy: some types of event are not shown in this timeline.
        </p>

        <div class="flex" :class="newestFirst ? 'flex-col-reverse' : 'flex-col'">
            @foreach ($timeline['days'] as $block)
                @if ($block['empty'])
                    <p class="border-b border-gray-100 px-4 py-2 text-xs italic text-gray-400">
                        Nothing recorded
                        @if ($block['from']->isSameDay($block['to']))
                            on {{ $block['from']->format('D, d M Y') }}
                        @else
                            from {{ $block['from']->format('d M') }} to {{ $block['to']->format('d M Y') }}
                        @endif
                    </p>
                @else
                    <div class="border-b border-gray-200" x-show="anyShown(@js(array_keys($block['counts'])))">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 bg-gray-50 px-4 py-1.5 break-after-avoid">
                            <p class="text-xs font-bold text-gray-800">
                                {{ $block['number'] >= 1 ? 'Day ' . $block['number'] : 'Before admission' }}
                                <span class="ml-1 font-semibold text-gray-500">{{ $block['date']->format('l, d M Y') }}</span>
                            </p>
                            @if ($block['roster'])
                                <p class="text-[11px] text-gray-500">
                                    <span class="font-semibold uppercase tracking-wide">On duty</span>
                                    @foreach ($block['roster'] as $shift)
                                        <span class="ml-2"><span class="font-semibold text-gray-700">{{ $shift['shift'] }}</span> {{ $shift['nurses'] }}</span>
                                    @endforeach
                                </p>
                            @endif
                        </div>

                        <ol class="flex divide-y divide-gray-100" :class="newestFirst ? 'flex-col-reverse divide-y-reverse' : 'flex-col'">
                            @foreach ($block['events'] as $event)
                                @php $colors = $timelineColors[$event['category']] ?? $timelineColors['admission']; @endphp
                                <li x-show="shown('{{ $event['category'] }}')"
                                    class="grid grid-cols-[3rem_minmax(0,1fr)] gap-x-3 px-4 py-1.5 text-sm break-inside-avoid sm:grid-cols-[3rem_minmax(0,1fr)_11rem] print:grid-cols-[3rem_minmax(0,1fr)_9rem]">
                                    <time datetime="{{ $event['at']->toIso8601String() }}"
                                        class="pt-0.5 font-mono text-xs text-gray-500">{{ $event['at']->format('H:i') }}</time>

                                    <div class="min-w-0">
                                        <p class="leading-snug">
                                            <span class="mr-1 inline-flex items-center gap-1 align-middle text-[10px] font-semibold uppercase tracking-wide {{ $colors['text'] }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $colors['dot'] }}"></span>{{ $shortLabels[$event['category']] ?? $event['category'] }}
                                            </span>
                                            <span class="font-semibold {{ $toneTitle[$event['tone']] ?? $toneTitle['default'] }}">{{ $event['title'] }}</span>
                                            @if ($event['badge'])
                                                <span class="ml-1 rounded px-1.5 py-px align-middle text-[10px] font-bold {{ $toneBadge[$event['tone']] ?? $toneBadge['default'] }}">{{ $event['badge'] }}</span>
                                            @endif
                                            @if ($event['detail'])
                                                <span class="text-gray-700">&mdash; {{ $event['detail'] }}</span>
                                            @endif
                                        </p>
                                        @if ($event['note'])
                                            <p class="mt-0.5 whitespace-pre-line break-words text-xs text-gray-500">{{ $event['note'] }}</p>
                                        @endif
                                        @if ($event['by'])
                                            <p class="mt-0.5 text-[11px] text-gray-400 sm:hidden print:hidden">{{ $event['by'] }}</p>
                                        @endif
                                    </div>

                                    <p class="hidden truncate pt-0.5 text-right text-xs text-gray-500 sm:block print:block" title="{{ $event['by'] }}">
                                        {{ $event['by'] }}
                                    </p>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</section>
