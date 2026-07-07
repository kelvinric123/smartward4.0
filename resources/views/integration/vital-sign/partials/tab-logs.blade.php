{{-- API Logs tab: recent requests, filters and per-log detail modal. --}}
<div x-data="apiLogsComponent()" class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
    <div class="p-6 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-cyan-50">
        <div class="flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center">
                <div class="p-3 bg-blue-600 rounded-xl mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Recent API Logs</h3>
                    <p class="text-sm text-gray-500">Most recent API requests received <span x-show="categoryFilter !== 'all'" class="text-blue-600">(filtered)</span></p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                @if(!isset($isFiltered) || !$isFiltered)
                    <select x-model="categoryFilter"
                        class="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="all">All Categories</option>
                        <option value="vital-signs">Vital Signs</option>
                        <option value="ping">Ping</option>
                        <option value="login">Login</option>
                        <option value="patients">Patients</option>
                        <option value="monitor">Monitor/Device</option>
                    </select>
                @endif
                <form action="{{ route('vital-sign-integration.logs.clear') }}" method="POST"
                    onsubmit="return confirm('Are you sure you want to clear all API logs?')">
                    @csrf
                    <input type="hidden" name="api_user_id" value="{{ request('api_user_id') }}">
                    <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors text-sm font-medium">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Clear Logs
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="p-6">
        <div class="mb-6 bg-blue-50/50 rounded-xl p-4 border border-blue-100">
            <form action="{{ route('vital-sign-integration.index') }}" method="GET" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">API User</label>
                    <select name="api_user_id" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="all">All Users</option>
                        @foreach($apiUsers as $user)
                            <option value="{{ $user->id }}" {{ request('api_user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Duration</label>
                    <select name="duration" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="" {{ request('duration') == '' ? 'selected' : '' }}>All Time</option>
                        <option value="24h" {{ request('duration') == '24h' ? 'selected' : '' }}>Last 24 Hours</option>
                        <option value="7d" {{ request('duration') == '7d' ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="30d" {{ request('duration') == '30d' ? 'selected' : '' }}>Last 30 Days</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-sm transition-colors text-sm flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filter
                    </button>

                    <a href="{{ route('vital-sign-integration.logs.export', request()->all()) }}"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg shadow-sm transition-colors text-sm flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export CSV
                    </a>

                    <a href="{{ route('vital-sign-integration.logs.print', request()->all()) }}" target="_blank"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-lg shadow-sm transition-colors text-sm flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print
                    </a>
                </div>
            </form>
        </div>

        @if($recentLogs->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 mb-4">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">User</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Endpoint</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Response Time</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">IP</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($recentLogs as $log)
                            <tr @if(!isset($isFiltered) || !$isFiltered) x-show="matchesFilter('{{ $log->endpoint }}')" @endif
                                @click="showLogDetails({{ json_encode([
                                    'created_at' => $log->created_at->format('M d, Y H:i:s'),
                                    'user' => $log->apiUser->name ?? 'Unknown',
                                    'endpoint' => $log->endpoint,
                                    'method' => $log->method,
                                    'status_code' => $log->status_code,
                                    'response_time_ms' => $log->response_time_ms,
                                    'ip_address' => $log->ip_address,
                                    'request_data' => $log->request_data,
                                    'response_data' => $log->response_data,
                                    'debug_data' => $log->debug_data,
                                ]) }})" class="hover:bg-blue-50 transition-colors cursor-pointer">
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $log->created_at->format('M d, H:i:s') }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $log->apiUser->name ?? 'Unknown' }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs font-medium px-2 py-0.5 rounded {{ $log->method === 'POST' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">{{ $log->method }}</span>
                                    <code class="ml-1 text-xs text-gray-600">{{ $log->endpoint }}</code>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-xs font-medium px-2 py-0.5 rounded {{ $log->status_code >= 200 && $log->status_code < 300 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $log->status_code }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $log->response_time_ms }}ms</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $log->ip_address }}</td>
                                <td class="px-4 py-3">
                                    <button type="button" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        Details
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(isset($isFiltered) && $isFiltered && $recentLogs instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4">
                        {{ $recentLogs->links() }}
                    </div>
                @endif
            </div>
        @else
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-gray-500">No API logs match your filter.</p>
            </div>
        @endif
    </div>

    <!-- Log Details Modal -->
    <div x-show="showDetailsModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
        x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" @click="showDetailsModal = false"></div>

            <div class="relative inline-block w-full max-w-4xl p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="flex justify-between items-center mb-4 flex-shrink-0">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        API Log Details
                    </h3>
                    <button @click="showDetailsModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="overflow-y-auto flex-1 pr-2">
                    <!-- Overview -->
                    <div class="bg-gray-50 rounded-xl p-4 mb-4">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-gray-500">Time</span>
                                <p class="text-gray-800" x-text="selectedLog?.created_at"></p>
                            </div>
                            <div>
                                <span class="font-medium text-gray-500">User</span>
                                <p class="text-gray-800" x-text="selectedLog?.user"></p>
                            </div>
                            <div>
                                <span class="font-medium text-gray-500">Endpoint</span>
                                <p class="text-gray-800">
                                    <span x-text="selectedLog?.method"
                                        :class="selectedLog?.method === 'POST' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'"
                                        class="text-xs font-medium px-2 py-0.5 rounded"></span>
                                    <code class="ml-1 text-xs" x-text="selectedLog?.endpoint"></code>
                                </p>
                            </div>
                            <div>
                                <span class="font-medium text-gray-500">Status</span>
                                <p>
                                    <span x-text="selectedLog?.status_code"
                                        :class="selectedLog?.status_code >= 200 && selectedLog?.status_code < 300 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                        class="text-xs font-medium px-2 py-0.5 rounded"></span>
                                    <span class="text-gray-500 text-xs ml-1" x-text="selectedLog?.response_time_ms + 'ms'"></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="border-b border-gray-200 mb-4">
                        <nav class="flex space-x-4">
                            <button @click="activeTab = 'request'"
                                :class="activeTab === 'request' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                class="py-2 px-4 border-b-2 font-medium text-sm transition-colors">
                                Request Data
                            </button>
                            <button @click="activeTab = 'response'"
                                :class="activeTab === 'response' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                class="py-2 px-4 border-b-2 font-medium text-sm transition-colors">
                                Response Data
                            </button>
                            <button @click="activeTab = 'debug'" x-show="selectedLog?.debug_data"
                                :class="activeTab === 'debug' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                class="py-2 px-4 border-b-2 font-medium text-sm transition-colors flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                                Debug Info
                            </button>
                        </nav>
                    </div>

                    <!-- Request Data Tab -->
                    <div x-show="activeTab === 'request'" class="bg-slate-900 rounded-xl p-4">
                        <pre class="text-sm text-green-400 font-mono whitespace-pre-wrap overflow-x-auto"
                            x-text="JSON.stringify(selectedLog?.request_data, null, 2)"></pre>
                    </div>

                    <!-- Response Data Tab -->
                    <div x-show="activeTab === 'response'" class="bg-slate-900 rounded-xl p-4">
                        <pre class="text-sm text-green-400 font-mono whitespace-pre-wrap overflow-x-auto"
                            x-text="JSON.stringify(selectedLog?.response_data, null, 2)"></pre>
                    </div>

                    <!-- Debug Info Tab -->
                    <div x-show="activeTab === 'debug'" class="space-y-4">
                        <!-- Processing Steps -->
                        <div class="bg-gray-50 rounded-xl p-4" x-show="selectedLog?.debug_data?.processing_steps">
                            <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                Processing Steps
                            </h4>
                            <div class="space-y-2">
                                <template x-for="(step, index) in selectedLog?.debug_data?.processing_steps" :key="index">
                                    <div class="flex items-start">
                                        <span class="w-6 h-6 flex items-center justify-center rounded-full text-xs font-bold mr-2"
                                            :class="step.includes('FAILED') ? 'bg-red-100 text-red-700' : step.includes('SUCCESS') ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'"
                                            x-text="index + 1"></span>
                                        <span class="text-sm text-gray-700"
                                            :class="step.includes('FAILED') ? 'text-red-600 font-medium' : step.includes('SUCCESS') ? 'text-green-600 font-medium' : ''"
                                            x-text="step"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Patient Lookup -->
                        <div class="bg-gray-50 rounded-xl p-4"
                            x-show="selectedLog?.debug_data?.patient_lookup && Object.keys(selectedLog?.debug_data?.patient_lookup || {}).length > 0">
                            <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Patient Lookup
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                                <template x-for="(value, key) in selectedLog?.debug_data?.patient_lookup" :key="key">
                                    <div class="bg-white p-2 rounded-lg border border-gray-200">
                                        <span class="text-gray-500 text-xs" x-text="key.replace(/_/g, ' ')"></span>
                                        <p class="font-medium text-gray-800 truncate" x-text="value ?? 'null'"></p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Nurse Binding Info -->
                        <div class="bg-gray-50 rounded-xl p-4"
                            x-show="selectedLog?.debug_data?.binding_info && Object.keys(selectedLog?.debug_data?.binding_info || {}).length > 0">
                            <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                Nurse Binding
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <template x-for="(value, key) in selectedLog?.debug_data?.binding_info" :key="key">
                                    <div class="bg-white p-2 rounded-lg border border-gray-200">
                                        <span class="text-gray-500 text-xs" x-text="key.replace(/_/g, ' ')"></span>
                                        <p class="font-medium text-gray-800" x-text="String(value)"></p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Parsing Details -->
                        <div class="bg-gray-50 rounded-xl p-4"
                            x-show="selectedLog?.debug_data?.parsing_details && Object.keys(selectedLog?.debug_data?.parsing_details || {}).length > 0">
                            <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Parsing Details
                            </h4>
                            <div class="space-y-3">
                                <template x-for="(value, key) in selectedLog?.debug_data?.parsing_details" :key="key">
                                    <div>
                                        <span class="text-sm font-medium text-gray-600 capitalize" x-text="key.replace(/_/g, ' ')"></span>
                                        <div class="bg-slate-800 rounded-lg p-3 mt-1">
                                            <pre class="text-xs text-green-400 font-mono whitespace-pre-wrap" x-text="JSON.stringify(value, null, 2)"></pre>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Vital Sign Creation -->
                        <div class="bg-gray-50 rounded-xl p-4"
                            x-show="selectedLog?.debug_data?.vital_sign_creation && Object.keys(selectedLog?.debug_data?.vital_sign_creation || {}).length > 0">
                            <h4 class="font-bold text-gray-800 mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                                Vital Sign Creation
                            </h4>
                            <div class="bg-slate-800 rounded-lg p-3">
                                <pre class="text-xs text-green-400 font-mono whitespace-pre-wrap"
                                    x-text="JSON.stringify(selectedLog?.debug_data?.vital_sign_creation, null, 2)"></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end flex-shrink-0">
                    <button @click="showDetailsModal = false"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
