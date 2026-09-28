<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Beds') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Manage bed assignments and availability</p>
            </div>
            <a href="{{ route('beds.create') }}" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-700 hover:to-accent-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5">
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

            @if (session('error'))
                <div class="mb-6 bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-blue-100">
                            <thead>
                                <tr class="bg-gradient-to-r from-brand-50 to-accent-50">
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
                                            {{-- Manage: deactivate / activate, and maintenance. The menu is moved
                                                 to <body> so the table's scroll container cannot clip it. --}}
                                            @php
                                                // After the sync in index(), occupied/reserved means a patient is in or booked into the bed
                                                $bedTaken = in_array($bed->status, ['occupied', 'reserved'], true);
                                            @endphp
                                            <div class="relative inline-block mr-2" x-data="{
                                                    open: false,
                                                    above: false,
                                                    top: 0,
                                                    left: 0,
                                                    toggle() {
                                                        if (this.open) { this.open = false; return; }
                                                        const box = this.$refs.trigger.getBoundingClientRect();
                                                        this.above = window.innerHeight - box.bottom < 160;
                                                        this.top = this.above ? box.top - 4 : box.bottom + 4;
                                                        this.left = box.right;
                                                        this.open = true;
                                                    },
                                                }"
                                                @keydown.escape.window="open = false"
                                                @scroll.window.capture="open = false"
                                                @resize.window="open = false">
                                                <button type="button" x-ref="trigger" @click="toggle()" aria-haspopup="menu" :aria-expanded="open.toString()"
                                                    class="inline-flex items-center px-3 py-1.5 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 rounded-lg transition-colors">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    </svg>
                                                    Manage
                                                    <svg class="w-3.5 h-3.5 ml-1 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                    </svg>
                                                </button>
                                                <template x-teleport="body">
                                                    <div x-show="open" x-cloak role="menu"
                                                        @click.outside="if (!$refs.trigger.contains($event.target)) open = false"
                                                        :style="{ top: top + 'px', left: left + 'px' }"
                                                        :class="above ? '-translate-y-full' : ''"
                                                        class="fixed z-50 -translate-x-full w-64 rounded-lg bg-white shadow-xl border border-gray-200 py-1 text-left text-sm">
                                                        <form action="{{ route('beds.deactivate', $bed) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" role="menuitem" class="w-full flex items-start gap-2 px-4 py-2 text-left text-gray-700 hover:bg-yellow-50">
                                                                <svg class="w-4 h-4 mt-0.5 text-yellow-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                                </svg>
                                                                <span>
                                                                    <span class="block font-medium">{{ $bed->is_active ? 'Deactivate' : 'Activate' }}</span>
                                                                    <span class="block text-xs text-gray-500">{{ $bed->is_active ? 'Hide this bed from use' : 'Put this bed back in use' }}</span>
                                                                </span>
                                                            </button>
                                                        </form>
                                                        <form action="{{ route('beds.maintenance', $bed) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" role="menuitem" class="w-full flex items-start gap-2 px-4 py-2 text-left text-gray-700 hover:bg-gray-50">
                                                                <svg class="w-4 h-4 mt-0.5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085"/>
                                                                </svg>
                                                                <span>
                                                                    @if ($bed->isUnderMaintenance())
                                                                        <span class="block font-medium">End maintenance</span>
                                                                        <span class="block text-xs text-gray-500">Put this bed back in service</span>
                                                                    @else
                                                                        <span class="block font-medium">Maintenance</span>
                                                                        @if ($bedTaken)
                                                                            <span class="block text-xs text-red-600">A patient is in or booked into this bed: discharge, transfer or move them first</span>
                                                                        @else
                                                                            <span class="block text-xs text-gray-500">Take this bed out of service</span>
                                                                        @endif
                                                                    @endif
                                                                </span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </template>
                                            </div>
                                            <form action="{{ route('beds.destroy', $bed) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" onclick="confirmDelete(event, 'Are you sure you want to delete this bed?')" class="inline-flex items-center px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors">
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

