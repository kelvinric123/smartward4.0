{{--
    The evidence behind patient acuity and acuity-based staffing: the principles
    the page applies, each with its source and page, and the reference list.
    Shown on the Patient acuity tab, in printouts and on the shared view, so it
    must not depend on the signed-in user or link into the app.

    Optional: $compact (bool) for print: the principles as a short list.
    Each citation follows only the sentence it supports: a principle's 'more'
    sentences carry their own.
--}}
@php
    $compact = $compact ?? false;
    $primary = \App\Support\AcuityReferences::primary();
@endphp

<div class="acuity-references space-y-3 {{ $compact ? 'text-[11px]' : 'text-sm' }} text-gray-700">
    @if ($compact)
        <ul class="list-disc list-inside space-y-0.5">
            @foreach (\App\Support\AcuityReferences::PRINCIPLES as $principle)
                <li>
                    <span class="font-semibold text-gray-800">{{ $principle['title'] }}.</span> {{ $principle['text'] }} <span class="text-gray-500">({{ $principle['cite'] }})</span>
                    @foreach ($principle['more'] ?? [] as $more)
                        {{ $more['text'] }} <span class="text-gray-500">({{ $more['cite'] }})</span>
                    @endforeach
                </li>
            @endforeach
        </ul>
    @else
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach (\App\Support\AcuityReferences::PRINCIPLES as $principle)
                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-3 break-inside-avoid">
                    <div class="font-semibold text-gray-800">{{ $principle['title'] }}</div>
                    @if (!empty($principle['quote']))
                        <p class="mt-1 italic text-gray-700">&ldquo;{{ $principle['quote'] }}&rdquo;</p>
                    @endif
                    <p class="mt-1">
                        {{ $principle['text'] }} <span class="text-gray-500">({{ $principle['cite'] }})</span>
                        @foreach ($principle['more'] ?? [] as $more)
                            {{ $more['text'] }} <span class="text-gray-500">({{ $more['cite'] }})</span>
                        @endforeach
                    </p>
                    <p class="mt-1.5 text-xs text-indigo-700"><span class="font-semibold">Here:</span> {{ $principle['applied'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div>
        <h4 class="font-semibold text-gray-800 {{ $compact ? 'text-xs' : 'text-sm' }}">References</h4>
        <ol class="mt-1 space-y-1 {{ $compact ? '' : 'text-xs' }}">
            @foreach (\App\Support\AcuityReferences::sources() as $key => $source)
                <li class="pl-6" style="text-indent: -1.5rem">
                    {{ $source['citation'] }}
                    @if ($source['url'])
                        <span class="break-all text-gray-500">{{ $source['url'] }}</span>
                    @endif
                    @if ($source['cited'])
                        <span class="text-gray-500">(As cited in {{ $primary['short'] }}.)</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</div>
