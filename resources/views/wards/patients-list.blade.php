<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Patients</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="p-4">
        <!-- Header with current ward + search -->
        <div class="mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Ward Patients</h2>
                <p class="text-sm text-gray-500">
                    {{ $ward ? $ward->ward_name : 'All Wards' }}
                    @if($ward && $ward->specialties)
                        &mdash; {{ $ward->specialties }}
                    @endif
                </p>
            </div>

            <form method="GET" action="{{ route('ward.patients-list') }}" class="flex items-center space-x-2">
                @if($wardId)
                    <input type="hidden" name="ward_id" value="{{ $wardId }}">
                @endif
                <div class="relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search by MRN or name..." autocomplete="off"
                        class="pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm min-w-[240px]">
                    <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                        </svg>
                    </span>
                </div>
                <button type="submit"
                    class="px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Search
                </button>
            </form>
        </div>

        <!-- Patients Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                MRN</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ward</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Bed</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Consultant</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Nurse</th>
                            <th scope="col"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($patients as $patient)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $patient->mrn }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <div class="font-semibold">{{ $patient->name }}</div>
                                    <div class="text-xs text-gray-500">
                                        @if($patient->gender)
                                            {{ ucfirst($patient->gender) }}
                                        @endif
                                        @if($patient->age)
                                            @if($patient->gender) &middot; @endif
                                            {{ $patient->age }} yrs
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $patient->ward ? $patient->ward->ward_name : '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $patient->bed_number ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if($patient->status === 'admitted')
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Admitted
                                        </span>
                                    @elseif($patient->status === 'prebook')
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Prebook
                                        </span>
                                    @else
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-700">
                                            {{ ucfirst($patient->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $patient->consultant ? $patient->consultant->name : '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $patient->nurse ? $patient->nurse->name : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <div class="space-y-1 text-xs">
                                        @if($patient->admitted_at)
                                            <div>
                                                <span class="font-medium">Admitted:</span>
                                                {{ $patient->admitted_at instanceof \Carbon\Carbon ? $patient->admitted_at->format('Y-m-d H:i') : $patient->admitted_at }}
                                            </div>
                                        @elseif($patient->booked_at)
                                            <div>
                                                <span class="font-medium">Booked:</span>
                                                {{ $patient->booked_at instanceof \Carbon\Carbon ? $patient->booked_at->format('Y-m-d H:i') : $patient->booked_at }}
                                            </div>
                                        @endif
                                        @if($patient->phone)
                                            <div>
                                                <span class="font-medium">Phone:</span>
                                                {{ $patient->phone }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                                    No patients found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        @if($patients->hasPages())
            <div class="mt-4">
                {{ $patients->links() }}
            </div>
        @endif
    </div>
</body>

</html>