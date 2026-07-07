{{-- API Users tab: manage API credentials for gateway devices. --}}
<div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-emerald-100">
    <div class="p-6 border-b border-emerald-100 bg-gradient-to-r from-emerald-50 to-teal-50">
        <div class="flex justify-between items-center">
            <div class="flex items-center">
                <div class="p-3 bg-emerald-600 rounded-xl mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">API Users</h3>
                    <p class="text-sm text-gray-500">Manage API credentials for Gateway devices</p>
                </div>
            </div>
            <button @click="showAddUserModal = true"
                class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add API User
            </button>
        </div>
    </div>

    <div class="p-6">
        @if($apiUsers->count() > 0)
            <div class="grid gap-4">
                @foreach($apiUsers as $user)
                    <div class="border border-gray-200 rounded-xl p-5 hover:border-emerald-300 hover:shadow-md transition-all bg-white">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <svg class="w-5 h-5 mr-1.5 text-rose-500 flex-shrink-0" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" title="Scannable Name">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h2m10 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                    </svg>
                                    <h4 class="text-lg font-bold text-gray-800">{{ $user->name }}</h4>
                                    <span class="ml-2 px-2 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} rounded-full">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    @if($user->hasValidToken())
                                        <span class="ml-2 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 rounded-full">Token Valid</span>
                                    @endif
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm text-gray-600">
                                    <div>
                                        <span class="font-medium">Username:</span>
                                        <code class="bg-gray-100 px-2 py-0.5 rounded">{{ $user->username }}</code>
                                    </div>
                                    <div>
                                        <span class="font-medium">Requests:</span>
                                        {{ number_format($user->request_count) }}
                                    </div>
                                    <div>
                                        <span class="font-medium">Last Login:</span>
                                        {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                                    </div>
                                </div>
                                @if($user->description)
                                    <p class="text-sm text-gray-500 mt-2">{{ $user->description }}</p>
                                @endif
                            </div>
                            <div class="flex items-center space-x-2 ml-4">
                                <button @click="editUser({{ json_encode($user) }})"
                                    class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition-colors text-sm font-medium">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit
                                </button>
                                <form action="{{ route('vital-sign-integration.api-user.destroy', $user) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" onclick="confirmDelete(event, 'Are you sure you want to delete this item?')"
                                        class="inline-flex items-center px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors text-sm font-medium">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                <h4 class="text-lg font-medium text-gray-600 mb-2">No API Users</h4>
                <p class="text-gray-500 mb-4">Create an API user to allow Gateway devices to send vital signs.</p>
                <button @click="showAddUserModal = true"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add API User
                </button>
            </div>
        @endif
    </div>
</div>
