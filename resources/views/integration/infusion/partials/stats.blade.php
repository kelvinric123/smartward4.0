{{-- Summary counters, shown above the tabs so they read on every section. --}}
    <!-- Stats Overview -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
        <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 bg-indigo-100 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-800">{{ $stats['total_pumps'] }}</div>
                    <div class="text-xs text-gray-500">Total Pumps</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-800">{{ $stats['active_pumps'] }}</div>
                    <div class="text-xs text-gray-500">Active Pumps</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-800">{{ $stats['active_infusions'] }}</div>
                    <div class="text-xs text-gray-500">Active Infusions</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 bg-amber-100 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-xl font-bold text-amber-600">{{ $stats['warnings'] }}</div>
                    <div class="text-xs text-gray-500">Warnings</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 bg-red-100 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>
                <div>
                    <div class="text-xl font-bold text-red-600">{{ $stats['alarms'] }}</div>
                    <div class="text-xs text-gray-500">Alarms</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-800">{{ $stats['total_hl7_messages'] }}</div>
                    <div class="text-xs text-gray-500">Total HL7 Msgs</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 bg-cyan-100 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-xl font-bold text-gray-800">{{ $stats['messages_today'] }}</div>
                    <div class="text-xs text-gray-500">Today</div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
            <div class="flex items-center">
                <div class="p-2 bg-rose-100 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-xl font-bold text-rose-600">{{ $stats['error_messages'] }}</div>
                    <div class="text-xs text-gray-500">Errors</div>
                </div>
            </div>
        </div>
    </div>
