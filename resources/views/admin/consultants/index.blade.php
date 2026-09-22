<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Consultants') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Manage consultant doctors and specialists</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('consultants.bulk-upload') }}"
                    class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    {{ __('Bulk Upload') }}
                </a>
                <a href="{{ route('consultants.create') }}"
                    class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add Consultant') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md"
                    role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8">
                    <x-list-search :route="route('consultants.index')" :value="$search"
                        placeholder="Search name, code, specialty, phone or email..." :paginator="$consultants"
                        noun="consultants" />
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-blue-100" id="dataTable">
                            <thead>
                                <tr class="bg-gradient-to-r from-blue-50 to-cyan-50">
                                    <th
                                        class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                        Name</th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                        Personnel Code</th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                        Specialty</th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                        Email</th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-blue-50">
                                @forelse ($consultants as $consultant)
                                    <tr class="hover:bg-blue-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="p-2 bg-cyan-100 rounded-lg mr-3">
                                                    <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                    </svg>
                                                </div>
                                                <span class="font-semibold text-gray-800">{{ $consultant->name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 text-sm">
                                            @if($consultant->personnel_code)
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 font-mono">
                                                    {{ $consultant->personnel_code }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 text-sm">
                                            @if($consultant->specialty)
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                    {{ $consultant->specialty->name }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 text-sm">{{ $consultant->email ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $consultant->is_active ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200' : 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 border border-red-200' }}">
                                                {{ $consultant->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('consultants.show', $consultant) }}"
                                                class="inline-flex items-center px-3 py-1.5 bg-cyan-100 hover:bg-cyan-200 text-cyan-700 rounded-lg transition-colors mr-2">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                View
                                            </a>
                                            <a href="{{ route('consultants.edit', $consultant) }}"
                                                class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition-colors mr-2">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit
                                            </a>
                                            <form action="{{ route('consultants.deactivate', $consultant) }}" method="POST"
                                                class="inline">
                                                @csrf
                                                <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 rounded-lg transition-colors mr-2">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                    {{ $consultant->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                            <form action="{{ route('consultants.destroy', $consultant) }}" method="POST"
                                                class="inline"
                                                >
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" onclick="confirmDelete(event, 'Are you sure you want to delete this item?')"
                                                    class="inline-flex items-center px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center">
                                                <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                <p class="text-gray-500 font-medium">No consultants found</p>
                                                @if ($search !== '')
                                                    <p class="text-gray-400 text-sm mt-1">Nothing in the consultants list matches
                                                        <span class="font-medium text-gray-500">"{{ $search }}"</span>.</p>
                                                    <a href="{{ route('consultants.index') }}"
                                                        class="mt-3 inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition">
                                                        Clear search</a>
                                                @else
                                                    <p class="text-gray-400 text-sm mt-1">Get started by adding your first consultant</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6">
                        {{ $consultants->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        });
    </script>
</x-app-layout>