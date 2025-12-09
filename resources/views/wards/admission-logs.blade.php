<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="p-4">
        <!-- Header with Filter -->
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Admission Logs</h2>
                <p class="text-sm text-gray-500">Bed status and admission activity</p>
            </div>
            <form method="GET" action="{{ route('ward.admission-logs') }}" class="flex flex-wrap items-center gap-2">
                <select name="ward_id" class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="">All Wards</option>
                    @foreach($wards as $ward)
                        <option value="{{ $ward->id }}" {{ $wardId == $ward->id ? 'selected' : '' }}>
                            {{ $ward->ward_name }}
                        </option>
                    @endforeach
                </select>

                <select name="action" class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="">All Actions</option>
                    @foreach($actions as $actionOption)
                        <option value="{{ $actionOption }}" {{ request('action') === $actionOption ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $actionOption)) }}
                        </option>
                    @endforeach
                </select>

                <input type="text" name="bed_number" value="{{ request('bed_number') }}" placeholder="Bed #" class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm w-28">

                <input type="text" name="search" value="{{ request('search') }}" placeholder="MRN / Patient" class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm w-40">

                <input type="date" name="from_date" value="{{ request('from_date') }}" class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">

                <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                    Filter
                </button>
                <a href="{{ route('ward.admission-logs') }}" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm border border-gray-300 hover:bg-gray-200">
                    Reset
                </a>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ward</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bed</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Consultant</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nurse</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
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
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $badgeClass }}">
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
                                            <div class="text-xs"><span class="font-medium">Gender:</span> {{ ucfirst($log->gender) }}</div>
                                        @endif
                                        @if($log->age)
                                            <div class="text-xs"><span class="font-medium">Age:</span> {{ $log->age }}</div>
                                        @endif
                                        @if($log->notes)
                                            <div class="text-xs"><span class="font-medium">Notes:</span> {{ \Illuminate\Support\Str::limit($log->notes, 50) }}</div>
                                        @endif
                                        @if($log->admitted_at)
                                            <div class="text-xs"><span class="font-medium">Admitted:</span> {{ $log->admitted_at instanceof \Carbon\Carbon ? $log->admitted_at->format('Y-m-d H:i') : $log->admitted_at }}</div>
                                        @endif
                                        @if($log->booked_at)
                                            <div class="text-xs"><span class="font-medium">Booked:</span> {{ $log->booked_at instanceof \Carbon\Carbon ? $log->booked_at->format('Y-m-d H:i') : $log->booked_at }}</div>
                                        @endif
                                        @if($log->action === 'discharge')
                                            <div class="text-xs text-gray-500">Bed released</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $log->user ? $log->user->name : 'System' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">
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
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</body>
</html>

