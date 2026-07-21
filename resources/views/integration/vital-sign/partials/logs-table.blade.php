{{-- Recent API logs table + daily summaries. Rendered inline when filtering,
     or returned as an HTML fragment by the logs.table lazy-load endpoint. --}}
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

@if(isset($summaries) && $summaries->count() > 0)
    <div class="mt-6 border-t border-gray-100 pt-6" x-data="{ showSummaries: false }">
        <button type="button" @click="showSummaries = !showSummaries"
            class="flex items-center text-sm font-bold text-gray-700 hover:text-blue-600 transition-colors">
            <svg class="w-4 h-4 mr-2 transition-transform" :class="showSummaries ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            Daily Summaries (last 30 days)
            <span class="ml-2 px-1.5 py-0.5 text-xs font-bold rounded-full bg-gray-200 text-gray-600">{{ $summaries->count() }}</span>
        </button>
        <p class="text-xs text-gray-400 mt-1 ml-6">Aggregates preserved from logs that have been cleared or auto-pruned.</p>

        <div x-show="showSummaries" x-cloak class="overflow-x-auto mt-3">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-700 uppercase">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-700 uppercase">User</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-700 uppercase">Endpoint</th>
                        <th class="px-4 py-2 text-right text-xs font-bold text-gray-700 uppercase">Requests</th>
                        <th class="px-4 py-2 text-right text-xs font-bold text-gray-700 uppercase">Success</th>
                        <th class="px-4 py-2 text-right text-xs font-bold text-gray-700 uppercase">Errors</th>
                        <th class="px-4 py-2 text-right text-xs font-bold text-gray-700 uppercase">Avg / Max ms</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @foreach($summaries as $summary)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $summary->summary_date->format('M d, Y') }}</td>
                            <td class="px-4 py-2 text-sm font-medium text-gray-800">{{ $summary->apiUser->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-2">
                                <span class="text-xs font-medium px-2 py-0.5 rounded {{ $summary->method === 'POST' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">{{ $summary->method }}</span>
                                <code class="ml-1 text-xs text-gray-600">{{ $summary->endpoint }}</code>
                            </td>
                            <td class="px-4 py-2 text-sm text-right text-gray-800 font-medium">{{ number_format($summary->total_requests) }}</td>
                            <td class="px-4 py-2 text-sm text-right text-green-600">{{ number_format($summary->success_count) }}</td>
                            <td class="px-4 py-2 text-sm text-right {{ $summary->error_count > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">{{ number_format($summary->error_count) }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-600">{{ $summary->avg_response_time_ms ?? '-' }} / {{ $summary->max_response_time_ms ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
