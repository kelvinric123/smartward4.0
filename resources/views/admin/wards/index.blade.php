<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Wards') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Manage ward information and capacity</p>
            </div>
            <a href="{{ route('wards.create') }}" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-700 hover:to-accent-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Add Ward') }}
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
                                <tr class="bg-gradient-to-r from-brand-50 to-accent-50">
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Ward Code</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Ward Name</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Hospital</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Ward Type</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Capacity</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Specialties</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-blue-50">
                                @forelse ($wards as $ward)
                                    <tr class="hover:bg-blue-50 transition-colors">
                                        <td class="px-3 py-3">
                                            <span class="font-semibold text-gray-800">{{ $ward->ward_code }}</span>
                                        </td>
                                        <td class="px-3 py-3 text-gray-600 text-sm">{{ $ward->ward_name }}</td>
                                        <td class="px-3 py-3 text-gray-600 text-sm">{{ $ward->hospital->name ?? '-' }}</td>
                                        <td class="px-3 py-3 text-sm whitespace-nowrap">
                                            @if ($ward->wardType)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $ward->wardType->name }}</span>
                                                @if ($ward->wardType->is_critical_care)
                                                    <span class="ml-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800"
                                                        title="Listed on the Critical Care Ward Dashboard">Critical care</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-gray-600 text-sm whitespace-nowrap">{{ $ward->capacity }} beds</td>
                                        <td class="px-3 py-3 text-gray-600 text-sm">{{ $ward->specialties ?? '-' }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $ward->is_active ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200' : 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 border border-red-200' }}">
                                                {{ $ward->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <a href="{{ route('wards.edit', $ward) }}"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors bg-blue-100 hover:bg-blue-200 text-blue-700" title="Edit ward">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    <span class="sr-only">Edit</span>
                                                </a>
                                                <form action="{{ route('wards.deactivate', $ward) }}" method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors bg-yellow-100 hover:bg-yellow-200 text-yellow-700"
                                                        title="{{ $ward->is_active ? 'Deactivate' : 'Activate' }} ward">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="{{ $ward->is_active ? 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' }}"/>
                                                        </svg>
                                                        <span class="sr-only">{{ $ward->is_active ? 'Deactivate' : 'Activate' }}</span>
                                                    </button>
                                                </form>
                                                <form action="{{ route('wards.destroy', $ward) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" onclick="confirmDelete(event, 'Are you sure you want to delete this ward? This will also delete all beds and admission logs!')"
                                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors bg-red-100 hover:bg-red-200 text-red-700" title="Delete ward">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                        <span class="sr-only">Delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center">
                                                <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                </svg>
                                                <p class="text-gray-500 font-medium">No wards found</p>
                                                <p class="text-gray-400 text-sm mt-1">Get started by adding your first ward</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6">
                        {{ $wards->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

