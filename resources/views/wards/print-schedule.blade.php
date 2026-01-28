<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ward Schedule Print - {{ $ward->ward_name }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @media print {
            @page {
                size: landscape;
                margin: 0.5cm;
            }

            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background: white;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body class="bg-white text-gray-900 font-sans antialiased text-xs">
    <div class="p-4 bg-white">
        <!-- Header -->
        <div class="flex justify-between items-start mb-4 border-b pb-4">
            <div>
                <h1 class="text-xl font-bold text-gray-800">{{ $ward->ward_name }} Schedule</h1>
                <p class="text-sm text-gray-500">
                    Range: {{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}
                </p>
                <p class="text-xs text-gray-400 mt-1">Printed on {{ now()->format('d M Y H:i A') }}</p>
            </div>
            <div class="no-print flex gap-2">
                <button onclick="window.print()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700">
                    Print / Save PDF
                </button>
                <button onclick="window.close()"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300">
                    Close
                </button>
            </div>
        </div>

        <!-- Schedule Table -->
        <table class="w-full border-collapse border border-gray-300">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 p-2 text-left w-24">Bed</th>
                    <th class="border border-gray-300 p-2 text-center w-16">Shift</th>
                    @foreach($dateRange as $date)
                        <th class="border border-gray-300 p-2 text-center min-w-[100px]">
                            <div class="font-bold">{{ $date->format('D') }}</div>
                            <div class="text-[10px] text-gray-500">{{ $date->format('d/m') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($beds as $bed)
                    @foreach($shifts as $shiftIndex => $shift)
                        <tr class="{{ $loop->parent->even ? 'bg-gray-50' : 'bg-white' }}">
                            @if($loop->first)
                                <td class="border border-gray-300 p-2 align-top font-bold" rowspan="3">
                                    {{ $bed->bed_display_name ?? $bed->bed_number }}
                                    @if($bed->patient)
                                        <div
                                            class="mt-1 font-normal text-[10px] text-blue-800 bg-blue-50 px-1 py-0.5 rounded border border-blue-100 truncate max-w-[80px]">
                                            {{ Str::limit($bed->patient->name, 10) }}
                                        </div>
                                    @endif
                                </td>
                            @endif
                            <td class="border border-gray-300 p-1 text-center font-semibold text-[10px] bg-gray-50">
                                {{ $shift }}
                            </td>
                            @foreach($dateRange as $date)
                                @php
                                    $key = $bed->id . '|' . $date->toDateString() . '|' . $shift;
                                    $assignment = $assignments[$key] ?? null;
                                @endphp
                                <td class="border border-gray-300 p-1 align-top h-12">
                                    @if($assignment)
                                        <div class="font-bold text-gray-900 leading-tight">
                                            {{ $assignment->nurse->name ?? 'Unknown' }}
                                        </div>
                                        @if($assignment->nurse && $assignment->nurse->taggingNurses && $assignment->nurse->taggingNurses->isNotEmpty())
                                            <div class="mt-0.5 text-[9px] text-purple-700 flex flex-wrap gap-0.5">
                                                <span class="font-semibold text-purple-600 mr-0.5">Tag:</span>
                                                @foreach($assignment->nurse->taggingNurses as $tagNurse)
                                                    <span>{{ $tagNurse->name }}</span>{{ !$loop->last ? ',' : '' }}
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 border-t pt-4 text-[10px] text-gray-500 flex justify-between">
            <span>System generated report.</span>
            <span>Page <span class="page-number"></span></span>
        </div>
    </div>

    <script>
        window.onload = function () {
            // Optional: Auto print
            // window.print();
        }
    </script>
</body>

</html>