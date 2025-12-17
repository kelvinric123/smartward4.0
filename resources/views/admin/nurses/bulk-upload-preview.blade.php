<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Confirm Bulk Upload') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Review the staff to be imported</p>
            </div>
            <a href="{{ route('nurses.bulk-upload') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 hover:bg-gray-400 border border-transparent rounded-lg font-semibold text-sm text-gray-700 transition-all duration-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Upload
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Missing Nurses Warning --}}
            @if($missingNurses->count() > 0)
                <div class="bg-amber-50 border-l-4 border-amber-500 rounded-lg shadow-md overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-amber-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <div class="flex-1">
                                <h3 class="font-semibold text-amber-800 text-lg">Staff Missing From Upload</h3>
                                <p class="text-sm text-amber-700 mt-1">
                                    The following {{ $missingNurses->count() }} active nurse(s) were not found in the uploaded list. They may have retired or been transferred.
                                    <strong>Please deactivate them manually if needed.</strong>
                                </p>
                                <div class="mt-4 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-amber-200">
                                        <thead>
                                            <tr class="bg-amber-100/50">
                                                <th class="px-4 py-2 text-left text-xs font-bold text-amber-800 uppercase">Personnel Code</th>
                                                <th class="px-4 py-2 text-left text-xs font-bold text-amber-800 uppercase">Name</th>
                                                <th class="px-4 py-2 text-left text-xs font-bold text-amber-800 uppercase">Registration No.</th>
                                                <th class="px-4 py-2 text-left text-xs font-bold text-amber-800 uppercase">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-amber-100">
                                            @foreach($missingNurses as $nurse)
                                                <tr>
                                                    <td class="px-4 py-2 text-sm text-amber-900">
                                                        <span class="font-mono bg-amber-100 px-2 py-0.5 rounded">{{ $nurse->personnel_code }}</span>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-amber-900 font-medium">{{ $nurse->name }}</td>
                                                    <td class="px-4 py-2 text-sm text-amber-900">{{ $nurse->registration_number }}</td>
                                                    <td class="px-4 py-2">
                                                        <a href="{{ route('nurses.edit', $nurse) }}" target="_blank" class="text-amber-700 hover:text-amber-900 text-sm underline">
                                                            Edit/Deactivate
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Staff to be Added --}}
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-green-200">
                <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-green-200">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <div>
                            <h3 class="font-semibold text-green-800 text-lg">Staff to be Added</h3>
                            <p class="text-sm text-green-600">{{ count($toAdd) }} new nurse(s) will be imported</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    @if(count($toAdd) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-green-100">
                                <thead>
                                    <tr class="bg-green-50/50">
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Personnel Code</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Username</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Designation</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Department</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-green-50">
                                    @foreach($toAdd as $staff)
                                        <tr class="hover:bg-green-50/30">
                                            <td class="px-4 py-3 text-sm">
                                                <span class="font-mono bg-green-100 text-green-800 px-2 py-0.5 rounded">{{ $staff['personnel_code'] }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $staff['name'] }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $staff['registration_number'] }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $staff['email'] }}</td>
                                            <td class="px-4 py-3 text-sm">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ $staff['designation'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                    {{ $staff['department'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                            <p class="text-gray-500">No new nurses to add. All staff in the uploaded file already exist.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Existing Staff (Skipped) --}}
            @if(count($existing) > 0)
                <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-gray-200">
                    <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-slate-50 border-b border-gray-200">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                <h3 class="font-semibold text-gray-700 text-lg">Already Exists (Will be Skipped)</h3>
                                <p class="text-sm text-gray-500">{{ count($existing) }} nurse(s) already in the system</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead>
                                    <tr class="bg-gray-50/50">
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Personnel Code</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Username</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Department</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($existing as $staff)
                                        <tr class="text-gray-400">
                                            <td class="px-4 py-3 text-sm">
                                                <span class="font-mono bg-gray-100 px-2 py-0.5 rounded">{{ $staff['personnel_code'] }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-sm">{{ $staff['name'] }}</td>
                                            <td class="px-4 py-3 text-sm">{{ $staff['registration_number'] }}</td>
                                            <td class="px-4 py-3 text-sm">{{ $staff['department'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Action Buttons --}}
            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('nurses.bulk-upload') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 hover:bg-gray-400 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest transition ease-in-out duration-150">
                    Cancel
                </a>
                @if(count($toAdd) > 0)
                    <form method="POST" action="{{ route('nurses.bulk-upload.confirm') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Confirm Import ({{ count($toAdd) }} nurses)
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>










