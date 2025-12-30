<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Logs</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Print styles */
        @media print {
            body {
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }

            table {
                font-size: 10px !important;
            }

            th,
            td {
                padding: 4px 6px !important;
            }

            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 16px;
            }

            .print-header h1 {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 4px;
            }

            .print-header p {
                font-size: 12px;
                color: #666;
            }

            @page {
                size: landscape;
                margin: 10mm;
            }
        }

        .print-only {
            display: none;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="p-4">
        <!-- Print Header (only visible when printing) -->
        <div class="print-only print-header">
            <h1>Admission Logs Report</h1>
            <p>Generated on {{ date('Y-m-d H:i:s') }}</p>
        </div>

        <!-- Header with Filter -->
        <div class="mb-4 flex items-center justify-between no-print">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Admission Logs</h2>
                <p class="text-sm text-gray-500">Bed status and admission activity</p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Print Button -->
                <button onclick="openPrintModal()"
                    class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print
                </button>
            </div>
        </div>

        <!-- Filters Form -->
        <form method="GET" action="{{ route('ward.admission-logs') }}"
            class="mb-4 flex flex-wrap items-center gap-2 no-print">
            <select name="ward_id"
                class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                <option value="">All Wards</option>
                @foreach($wards as $ward)
                    <option value="{{ $ward->id }}" {{ $wardId == $ward->id ? 'selected' : '' }}>
                        {{ $ward->ward_name }}
                    </option>
                @endforeach
            </select>

            <select name="action"
                class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                <option value="">All Actions</option>
                @foreach($actions as $actionOption)
                    <option value="{{ $actionOption }}" {{ request('action') === $actionOption ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $actionOption)) }}
                    </option>
                @endforeach
            </select>

            <select name="source"
                class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                <option value="">All Sources</option>
                @foreach($sources ?? [] as $sourceOption)
                    <option value="{{ $sourceOption }}" {{ ($selectedSource ?? '') === $sourceOption ? 'selected' : '' }}>
                        {{ $sourceOption === 'adt' ? 'ADT/HIS' : ucfirst($sourceOption) }}
                    </option>
                @endforeach
            </select>

            <input type="text" name="bed_number" value="{{ request('bed_number') }}" placeholder="Bed #"
                class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm w-28">

            <input type="text" name="search" value="{{ request('search') }}" placeholder="MRN / Patient"
                class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm w-40">

            <input type="date" name="from_date" value="{{ request('from_date') }}"
                class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
            <input type="date" name="to_date" value="{{ request('to_date') }}"
                class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">

            <button type="submit"
                class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                Filter
            </button>
            <a href="{{ route('ward.admission-logs') }}"
                class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm border border-gray-300 hover:bg-gray-200">
                Reset
            </a>
        </form>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Time</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Source</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Action</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Patient</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ward</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Bed</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Consultant</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Nurse</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Details</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider no-print">
                                User</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $log->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @php
                                        $sourceStyles = [
                                            'manual' => 'bg-gray-100 text-gray-700',
                                            'adt' => 'bg-purple-100 text-purple-800',
                                        ];
                                        $sourceLabel = [
                                            'manual' => 'Manual',
                                            'adt' => 'ADT/HIS',
                                        ];
                                        $srcValue = $log->source ?? 'manual';
                                        $srcBadgeClass = $sourceStyles[$srcValue] ?? 'bg-gray-100 text-gray-700';
                                        $srcLabelText = $sourceLabel[$srcValue] ?? ucfirst($srcValue);
                                    @endphp
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $srcBadgeClass }}">
                                        {{ $srcLabelText }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @php
                                        $actionStyles = [
                                            'admit' => 'bg-green-100 text-green-800',
                                            'prebook' => 'bg-blue-100 text-blue-800',
                                            'check-in' => 'bg-purple-100 text-purple-800',
                                            'transfer' => 'bg-amber-100 text-amber-800',
                                            'discharge' => 'bg-gray-100 text-gray-800',
                                            'pending_discharge' => 'bg-orange-100 text-orange-800',
                                            'bed_release' => 'bg-slate-100 text-slate-800',
                                        ];
                                        $badgeClass = $actionStyles[$log->action] ?? 'bg-slate-100 text-slate-800';
                                    @endphp
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $badgeClass }}">
                                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <div class="font-medium">{{ $log->patient_name }}</div>
                                    <div class="text-gray-500 text-xs">MRN: {{ $log->mrn }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $log->ward ? $log->ward->ward_name : 'N/A' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $log->bed_number }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $log->consultant_name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $log->nurse_name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <div class="space-y-1">
                                        @if($log->gender)
                                            <div class="text-xs"><span class="font-medium">Gender:</span>
                                                {{ ucfirst($log->gender) }}</div>
                                        @endif
                                        @if($log->age)
                                            <div class="text-xs"><span class="font-medium">Age:</span> {{ $log->age }}</div>
                                        @endif
                                        @if($log->notes)
                                            <div class="text-xs"><span class="font-medium">Notes:</span>
                                                {{ \Illuminate\Support\Str::limit($log->notes, 50) }}</div>
                                        @endif
                                        @if($log->admitted_at)
                                            <div class="text-xs"><span class="font-medium">Admitted:</span>
                                                {{ $log->admitted_at instanceof \Carbon\Carbon ? $log->admitted_at->format('Y-m-d H:i') : $log->admitted_at }}
                                            </div>
                                        @endif
                                        @if($log->booked_at)
                                            <div class="text-xs"><span class="font-medium">Booked:</span>
                                                {{ $log->booked_at instanceof \Carbon\Carbon ? $log->booked_at->format('Y-m-d H:i') : $log->booked_at }}
                                            </div>
                                        @endif
                                        @if($log->action === 'discharge')
                                            <div class="text-xs text-gray-500">Bed released</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 no-print">
                                    {{ $log->user ? $log->user->name : ($log->source === 'adt' ? 'System (ADT)' : 'System') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-sm text-gray-500">
                                    No admission logs found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        @if($logs->hasPages())
            <div class="mt-4 no-print">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    <!-- Print Options Modal -->
    <div id="printModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center">
            <div class="fixed inset-0 bg-gray-500 opacity-75" onclick="closePrintModal()"></div>

            <div
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full relative z-10">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Print Options
                            </h3>

                            <div class="space-y-4">
                                <!-- Date Range Info -->
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Current Filters:</span><br>
                                        @if(request('from_date') || request('to_date'))
                                            Date: {{ request('from_date', 'Any') }} to {{ request('to_date', 'Any') }}<br>
                                        @endif
                                        @if(request('ward_id'))
                                            Ward:
                                            {{ $wards->where('id', request('ward_id'))->first()?->ward_name ?? 'Selected' }}<br>
                                        @endif
                                        @if(request('action'))
                                            Action: {{ ucfirst(str_replace('_', ' ', request('action'))) }}<br>
                                        @endif
                                        @if($selectedSource ?? null)
                                            Source:
                                            {{ $selectedSource === 'adt' ? 'ADT/HIS' : ucfirst($selectedSource) }}<br>
                                        @endif
                                        @if(!request('from_date') && !request('to_date') && !request('ward_id') && !request('action') && !($selectedSource ?? null))
                                            All records (no filters applied)
                                        @endif
                                    </p>
                                </div>

                                <!-- Print Options -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Columns to
                                        Include</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="inline-flex items-center text-sm">
                                            <input type="checkbox" id="col_time" checked
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Time</span>
                                        </label>
                                        <label class="inline-flex items-center text-sm">
                                            <input type="checkbox" id="col_source" checked
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Source</span>
                                        </label>
                                        <label class="inline-flex items-center text-sm">
                                            <input type="checkbox" id="col_action" checked
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Action</span>
                                        </label>
                                        <label class="inline-flex items-center text-sm">
                                            <input type="checkbox" id="col_patient" checked
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Patient</span>
                                        </label>
                                        <label class="inline-flex items-center text-sm">
                                            <input type="checkbox" id="col_ward" checked
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Ward</span>
                                        </label>
                                        <label class="inline-flex items-center text-sm">
                                            <input type="checkbox" id="col_bed" checked
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Bed</span>
                                        </label>
                                        <label class="inline-flex items-center text-sm">
                                            <input type="checkbox" id="col_consultant" checked
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Consultant</span>
                                        </label>
                                        <label class="inline-flex items-center text-sm">
                                            <input type="checkbox" id="col_nurse"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Nurse</span>
                                        </label>
                                        <label class="inline-flex items-center text-sm">
                                            <input type="checkbox" id="col_details"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Details</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Page Orientation -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Page Orientation</label>
                                    <div class="flex gap-4">
                                        <label class="inline-flex items-center text-sm">
                                            <input type="radio" name="orientation" value="landscape" checked
                                                class="border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Landscape</span>
                                        </label>
                                        <label class="inline-flex items-center text-sm">
                                            <input type="radio" name="orientation" value="portrait"
                                                class="border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2">Portrait</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button onclick="printLogs()" type="button"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print
                    </button>
                    <button onclick="closePrintModal()" type="button"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openPrintModal() {
            document.getElementById('printModal').classList.remove('hidden');
        }

        function closePrintModal() {
            document.getElementById('printModal').classList.add('hidden');
        }

        function printLogs() {
            // Get column visibility settings
            const columns = {
                time: document.getElementById('col_time').checked,
                source: document.getElementById('col_source').checked,
                action: document.getElementById('col_action').checked,
                patient: document.getElementById('col_patient').checked,
                ward: document.getElementById('col_ward').checked,
                bed: document.getElementById('col_bed').checked,
                consultant: document.getElementById('col_consultant').checked,
                nurse: document.getElementById('col_nurse').checked,
                details: document.getElementById('col_details').checked
            };

            // Get orientation
            const orientation = document.querySelector('input[name="orientation"]:checked').value;

            // Apply column visibility
            const table = document.querySelector('table');
            const headers = table.querySelectorAll('thead th');
            const rows = table.querySelectorAll('tbody tr');

            // Column indices
            const colIndices = {
                time: 0,
                source: 1,
                action: 2,
                patient: 3,
                ward: 4,
                bed: 5,
                consultant: 6,
                nurse: 7,
                details: 8
            };

            // Store original display states
            const originalStates = [];

            // Hide/show columns based on checkboxes
            Object.keys(columns).forEach(col => {
                const idx = colIndices[col];
                if (idx !== undefined) {
                    headers[idx].style.display = columns[col] ? '' : 'none';
                    rows.forEach(row => {
                        const cells = row.querySelectorAll('td');
                        if (cells[idx]) {
                            originalStates.push({ el: cells[idx], display: cells[idx].style.display });
                            cells[idx].style.display = columns[col] ? '' : 'none';
                        }
                    });
                }
            });

            // Update page style for orientation
            const styleEl = document.createElement('style');
            styleEl.id = 'print-orientation';
            styleEl.textContent = `@page { size: ${orientation}; margin: 10mm; }`;
            document.head.appendChild(styleEl);

            // Close modal before printing
            closePrintModal();

            // Print
            setTimeout(() => {
                window.print();

                // Restore column visibility after print
                setTimeout(() => {
                    Object.keys(columns).forEach(col => {
                        const idx = colIndices[col];
                        if (idx !== undefined) {
                            headers[idx].style.display = '';
                            rows.forEach(row => {
                                const cells = row.querySelectorAll('td');
                                if (cells[idx]) {
                                    cells[idx].style.display = '';
                                }
                            });
                        }
                    });

                    // Remove orientation style
                    const orientStyle = document.getElementById('print-orientation');
                    if (orientStyle) orientStyle.remove();
                }, 500);
            }, 100);
        }
    </script>
</body>

</html>