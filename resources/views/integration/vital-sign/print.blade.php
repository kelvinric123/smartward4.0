<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - API Logs Print</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @media print {
            @page {
                size: A4;
                margin: 1cm;
            }

            body {
                background: white;
                font-family: 'Figtree', sans-serif;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 4px;
                text-align: left;
            }

            th {
                background-color: #f3f4f6 !important;
                font-weight: bold;
            }

            .badge {
                border: 1px solid #000;
                padding: 1px 3px;
                border-radius: 3px;
                font-weight: bold;
            }
        }
    </style>
</head>

<body class="bg-gray-100 font-sans antialiased">
    <div class="max-w-[210mm] mx-auto bg-white p-8 min-h-screen">
        <div class="flex justify-between items-center mb-6 no-print">
            <button onclick="window.print()"
                class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">
                Print
            </button>
            <button onclick="window.close()"
                class="bg-gray-200 text-gray-700 px-4 py-2 rounded shadow hover:bg-gray-300 transition">
                Close
            </button>
        </div>

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">API Logs Report</h1>
            <p class="text-sm text-gray-500">Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
            <p class="text-sm text-gray-600 mt-2">
                <strong>Filter:</strong>
                User:
                {{ request('api_user_id') === 'all' || !request('api_user_id') ? 'All' : \App\Models\ApiUser::find(request('api_user_id'))->name ?? 'Unknown' }}
                |
                Duration: {{ request('duration') ? request('duration') : 'All Time' }}
            </p>
        </div>

        <table class="w-full text-left text-xs border border-gray-200">
            <thead>
                <tr class="bg-gray-100 border-b border-gray-200">
                    <th class="p-2 font-bold text-gray-700">Time</th>
                    <th class="p-2 font-bold text-gray-700">User</th>
                    <th class="p-2 font-bold text-gray-700">Method</th>
                    <th class="p-2 font-bold text-gray-700">Endpoint</th>
                    <th class="p-2 font-bold text-gray-700">Status</th>
                    <th class="p-2 font-bold text-gray-700">Ms</th>
                    <th class="p-2 font-bold text-gray-700">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($logs as $log)
                    <tr>
                        <td class="p-2">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td class="p-2 font-medium">{{ $log->apiUser->name ?? 'Unknown' }}</td>
                        <td class="p-2">
                            <span class="{{ $log->method === 'POST' ? 'text-green-700' : 'text-blue-700' }} font-bold">
                                {{ $log->method }}
                            </span>
                        </td>
                        <td class="p-2 font-mono text-[9px]">{{ Str::limit($log->endpoint, 40) }}</td>
                        <td class="p-2">
                            <span
                                class="{{ $log->status_code >= 200 && $log->status_code < 300 ? 'text-green-700' : 'text-red-700' }} font-bold">
                                {{ $log->status_code }}
                            </span>
                        </td>
                        <td class="p-2 text-gray-500">{{ $log->response_time_ms }}</td>
                        <td class="p-2 text-gray-500">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-4 text-center text-gray-500">No logs found for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-8 text-center text-xs text-gray-400">
            <p>End of Report - Total Records: {{ $logs->count() }}</p>
        </div>
    </div>

    <script>
        // Auto-print when opened
        window.onload = function () {
            // window.print();
        }
    </script>
</body>

</html>