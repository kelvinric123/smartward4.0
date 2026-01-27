<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Beds') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Manage bed assignments and availability</p>
            </div>
            <a href="{{ route('beds.create') }}" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Add Bed') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-blue-100">
                            <thead>
                                <tr class="bg-gradient-to-r from-blue-50 to-cyan-50">
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Bed ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Display Name</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Ward</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Section</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Patient</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nurse</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-blue-50">
                                @forelse ($beds as $bed)
                                    <tr class="hover:bg-blue-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <span class="font-semibold text-gray-800">{{ $bed->bed_id }}</span>
                                            <span class="block text-xs text-gray-500">{{ $bed->bed_number }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 text-sm">{{ $bed->bed_display_name }}</td>
                                        <td class="px-6 py-4 text-gray-600 text-sm">{{ $bed->ward->ward_name ?? '-' }}</td>
                                        <td class="px-6 py-4 text-gray-600 text-sm">{{ $bed->section ? 'Section ' . $bed->section : '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $statusColors = [
                                                    'available' => 'bg-green-100 text-green-800 border-green-200',
                                                    'occupied' => 'bg-red-100 text-red-800 border-red-200',
                                                    'reserved' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                    'maintenance' => 'bg-gray-100 text-gray-800 border-gray-200',
                                                ];
                                            @endphp
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border {{ $statusColors[$bed->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ ucfirst($bed->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 text-sm">{{ $bed->patient->name ?? '-' }}</td>
                                        <td class="px-6 py-4 text-gray-600 text-sm">{{ $bed->nurse->name ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('beds.edit', $bed) }}" class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition-colors mr-2">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                Edit
                                            </a>
                                            <form action="{{ route('beds.deactivate', $bed) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 rounded-lg transition-colors mr-2">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                    </svg>
                                                    {{ $bed->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                            <form action="{{ route('beds.destroy', $bed) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this bed?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center">
                                                <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                </svg>
                                                <p class="text-gray-500 font-medium">No beds found</p>
                                                <p class="text-gray-400 text-sm mt-1">Get started by adding your first bed</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6">
                        {{ $beds->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

