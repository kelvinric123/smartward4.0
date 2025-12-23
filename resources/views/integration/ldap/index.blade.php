<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('LDAP Integration') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Configure LDAP/Active Directory authentication and user
                    synchronization</p>
            </div>
            <button onclick="document.getElementById('ldapInfoModal').classList.remove('hidden')"
                class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                How LDAP Works
            </button>
        </div>
    </x-slot>

    <!-- LDAP Info Modal -->
    <div id="ldapInfoModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity"
                onclick="document.getElementById('ldapInfoModal').classList.add('hidden')"></div>

            <div
                class="relative inline-block w-full max-w-4xl p-8 my-8 text-left align-middle transition-all transform bg-gradient-to-br from-slate-900 to-slate-800 shadow-2xl rounded-3xl border border-slate-700">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl mr-4">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white">LDAP Authentication Flow</h3>
                    </div>
                    <button onclick="document.getElementById('ldapInfoModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-6">
                    <!-- Flow Diagram -->
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                        <div class="text-center p-4 bg-slate-700/50 rounded-xl border border-slate-600">
                            <div
                                class="w-16 h-16 mx-auto mb-3 bg-blue-500/20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-blue-300">1. User Login</p>
                            <p class="text-xs text-gray-400 mt-1">User enters AD credentials</p>
                        </div>
                        <div class="hidden md:flex justify-center">
                            <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </div>
                        <div class="text-center p-4 bg-slate-700/50 rounded-xl border border-slate-600">
                            <div
                                class="w-16 h-16 mx-auto mb-3 bg-purple-500/20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-purple-300">2. LDAP Bind</p>
                            <p class="text-xs text-gray-400 mt-1">SmartWard connects to AD server</p>
                        </div>
                        <div class="hidden md:flex justify-center">
                            <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </div>
                        <div class="text-center p-4 bg-slate-700/50 rounded-xl border border-slate-600">
                            <div
                                class="w-16 h-16 mx-auto mb-3 bg-green-500/20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-green-300">3. Authenticated</p>
                            <p class="text-xs text-gray-400 mt-1">User synced & logged in</p>
                        </div>
                    </div>

                    <!-- Detailed Steps -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-cyan-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            Step-by-Step Process
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-3">
                                <div class="flex items-start">
                                    <span
                                        class="w-6 h-6 bg-blue-600 text-white text-xs font-bold rounded-full flex items-center justify-center mr-3 mt-0.5">1</span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-200">Service Account Bind</p>
                                        <p class="text-xs text-gray-400">SmartWard uses the service account (Bind DN) to
                                            connect to the LDAP server securely via LDAPS on port 636.</p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <span
                                        class="w-6 h-6 bg-blue-600 text-white text-xs font-bold rounded-full flex items-center justify-center mr-3 mt-0.5">2</span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-200">User Search</p>
                                        <p class="text-xs text-gray-400">Searches for users in the Base DN matching the
                                            filter criteria (e.g., members of smart_ward security group).</p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <span
                                        class="w-6 h-6 bg-blue-600 text-white text-xs font-bold rounded-full flex items-center justify-center mr-3 mt-0.5">3</span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-200">Credential Verification</p>
                                        <p class="text-xs text-gray-400">User's entered credentials are validated
                                            directly against Active Directory.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-start">
                                    <span
                                        class="w-6 h-6 bg-green-600 text-white text-xs font-bold rounded-full flex items-center justify-center mr-3 mt-0.5">4</span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-200">Attribute Retrieval</p>
                                        <p class="text-xs text-gray-400">User attributes (displayName, mail,
                                            sAMAccountName) are fetched from AD.</p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <span
                                        class="w-6 h-6 bg-green-600 text-white text-xs font-bold rounded-full flex items-center justify-center mr-3 mt-0.5">5</span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-200">Local User Sync</p>
                                        <p class="text-xs text-gray-400">A local SmartWard account is created/updated
                                            with the AD information.</p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <span
                                        class="w-6 h-6 bg-green-600 text-white text-xs font-bold rounded-full flex items-center justify-center mr-3 mt-0.5">6</span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-200">Role Assignment</p>
                                        <p class="text-xs text-gray-400">Roles are assigned based on AD group membership
                                            via role mappings.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Key Configuration Fields -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-amber-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Configuration Fields Explained
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="bg-slate-800/50 rounded-lg p-3">
                                <p class="font-semibold text-cyan-300">Server URL</p>
                                <p class="text-gray-400 text-xs">The LDAP server address (e.g., ldaps://MYW02PPL003:636)
                                </p>
                            </div>
                            <div class="bg-slate-800/50 rounded-lg p-3">
                                <p class="font-semibold text-cyan-300">Bind DN</p>
                                <p class="text-gray-400 text-xs">Service account used to search AD (full Distinguished
                                    Name)</p>
                            </div>
                            <div class="bg-slate-800/50 rounded-lg p-3">
                                <p class="font-semibold text-cyan-300">Base DN</p>
                                <p class="text-gray-400 text-xs">The OU where user searches will be performed</p>
                            </div>
                            <div class="bg-slate-800/50 rounded-lg p-3">
                                <p class="font-semibold text-cyan-300">Filter String</p>
                                <p class="text-gray-400 text-xs">LDAP query filter to restrict which users can
                                    authenticate</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="document.getElementById('ldapInfoModal').classList.add('hidden')"
                        class="px-6 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-semibold rounded-lg transition-all">
                        Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="py-8" x-data="ldapManager()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md"
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

            @if (session('error'))
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md"
                    role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @if (!$ldapExtensionLoaded)
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-500 text-amber-800 px-6 py-4 rounded-lg shadow-md"
                    role="alert">
                    <div class="flex items-start">
                        <svg class="w-6 h-6 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p class="font-bold">PHP LDAP Extension Not Installed</p>
                            <p class="text-sm mt-1">The PHP LDAP extension is required for LDAP authentication to work.</p>
                            <div class="mt-3 text-sm bg-amber-100/50 rounded-lg p-3">
                                <p class="font-semibold mb-2">Installation Instructions:</p>
                                <ul class="list-disc list-inside space-y-1 text-xs">
                                    <li><strong>Windows (Laragon/XAMPP):</strong> Enable <code
                                            class="bg-amber-200/50 px-1 rounded">extension=ldap</code> in php.ini</li>
                                    <li><strong>Ubuntu/Debian:</strong> <code
                                            class="bg-amber-200/50 px-1 rounded">sudo apt install php-ldap && sudo systemctl restart apache2</code>
                                    </li>
                                    <li><strong>Alpine (Docker):</strong> <code
                                            class="bg-amber-200/50 px-1 rounded">apk add openldap-dev && docker-php-ext-install ldap</code>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-gradient-to-r from-emerald-50 to-green-50 border-l-4 border-emerald-500 text-emerald-800 px-6 py-4 rounded-lg shadow-md"
                    role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">PHP LDAP extension is loaded and ready</span>
                    </div>
                </div>
            @endif

            <!-- Synced LDAP Users Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-6 border-b border-blue-100 bg-gradient-to-r from-green-50 to-emerald-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Synced LDAP Users</h3>
                            <p class="text-sm text-gray-500">Users synchronized from LDAP/Active Directory</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    @if($ldapUsers->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Role</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">LDAP
                                            Config</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Last
                                            Synced</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($ldapUsers as $user)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <div
                                                        class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-medium text-sm mr-3">
                                                        {{ substr($user->name, 0, 1) }}
                                                    </div>
                                                    <span class="font-medium text-gray-800">{{ $user->name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600 text-sm">{{ $user->email }}</td>
                                            <td class="px-4 py-3">
                                                <span
                                                    class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-medium">{{ $availableRoles[$user->role] ?? $user->role }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600 text-sm">
                                                {{ $user->ldapConfiguration->name ?? '-' }}</td>
                                            <td class="px-4 py-3 text-gray-500 text-sm">
                                                {{ $user->ldap_synced_at ? $user->ldap_synced_at->diffForHumans() : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <p class="text-gray-500">No LDAP users have been synced yet.</p>
                            <p class="text-sm text-gray-400 mt-1">Configure an LDAP server and click "Sync Users" to import
                                users.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Add/Edit Configuration Modal -->
        <div x-show="showAddModal || showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModals()"></div>

                <div
                    class="relative inline-block w-full max-w-3xl p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-800"
                            x-text="showEditModal ? 'Edit LDAP Configuration' : 'Add LDAP Configuration'"></h3>
                        <button @click="closeModals()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form
                        :action="showEditModal ? '{{ url('ldap') }}/' + editingConfig.id : '{{ route('ldap.store') }}'"
                        method="POST">
                        @csrf
                        <template x-if="showEditModal">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Configuration Name *</label>
                                <input type="text" name="name" x-model="formData.name" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="e.g., phkl">
                            </div>

                            <!-- Server URL -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Server URL *</label>
                                <input type="text" name="server_url" x-model="formData.server_url" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="e.g., ldaps://MYW02PPL003:636">
                            </div>

                            <!-- IP Address -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">IP Address
                                    (Optional)</label>
                                <input type="text" name="ip_address" x-model="formData.ip_address"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="e.g., 192.168.17.11">
                            </div>

                            <!-- Port -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Port *</label>
                                <input type="number" name="port" x-model="formData.port" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="636">
                            </div>

                            <!-- Bind DN -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bind DN (LDAP Username)
                                    *</label>
                                <input type="text" name="bind_dn" x-model="formData.bind_dn" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono text-sm"
                                    placeholder="CN=ServiceAccount,OU=Service Accounts,DC=domain,DC=com">
                            </div>

                            <!-- Bind Password -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Bind Password <span x-show="!showEditModal">*</span>
                                    <span x-show="showEditModal" class="text-gray-400 font-normal">(leave empty to keep
                                        current)</span>
                                </label>
                                <input type="password" name="bind_password" x-model="formData.bind_password"
                                    :required="!showEditModal"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="••••••••">
                            </div>

                            <!-- Base DN -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Base DN (Search Base)
                                    *</label>
                                <input type="text" name="base_dn" x-model="formData.base_dn" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono text-sm"
                                    placeholder="OU=Users,OU=MyOrg,DC=domain,DC=com">
                            </div>

                            <!-- Filter String -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Filter String
                                    (Optional)</label>
                                <textarea name="filter_string" x-model="formData.filter_string" rows="2"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono text-sm"
                                    placeholder="(&(objectCategory=Person)(sAMAccountName=*))"></textarea>
                            </div>

                            <!-- Username Attribute -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username Attribute *</label>
                                <input type="text" name="username_attribute" x-model="formData.username_attribute"
                                    required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="sAMAccountName">
                            </div>

                            <!-- Email Attribute -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Attribute *</label>
                                <input type="text" name="email_attribute" x-model="formData.email_attribute" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="mail">
                            </div>

                            <!-- Name Attribute -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Display Name Attribute
                                    *</label>
                                <input type="text" name="name_attribute" x-model="formData.name_attribute" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="displayName">
                            </div>

                            <!-- Checkboxes -->
                            <div class="flex items-center space-x-6">
                                <label class="flex items-center">
                                    <input type="checkbox" name="use_ssl" x-model="formData.use_ssl"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">Use SSL (LDAPS)</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" x-model="formData.is_active"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">Active</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_default" x-model="formData.is_default"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">Default</span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="closeModals()"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="button" @click="testConnectionFromForm()" :disabled="testingForm"
                                class="px-4 py-2 bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium rounded-lg transition-colors inline-flex items-center">
                                <svg x-show="!testingForm" class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <svg x-show="testingForm" class="w-4 h-4 mr-2 animate-spin" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                Test Connection
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                                <span x-text="showEditModal ? 'Update Configuration' : 'Create Configuration'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Role Mapping Modal -->
        <div x-show="showRoleMappingModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                    @click="showRoleMappingModal = false"></div>

                <div
                    class="relative inline-block w-full max-w-lg p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-800">Add Role Mapping</h3>
                        <button @click="showRoleMappingModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form :action="'{{ url('ldap') }}/' + selectedConfigId + '/role-mapping'" method="POST">
                        @csrf

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">LDAP Group Name *</label>
                                <input type="text" name="ldap_group_name" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="e.g., Smart Ward Users">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">LDAP Group DN *</label>
                                <textarea name="ldap_group_dn" rows="3" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono text-sm"
                                    placeholder="CN=MyGroup,OU=Security Groups,DC=domain,DC=com"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Map to Local Role *</label>
                                <select name="local_role" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach($availableRoles as $roleKey => $roleName)
                                        <option value="{{ $roleKey }}">{{ $roleName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="showRoleMappingModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                                Add Mapping
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Test Result Toast -->
        <div x-show="testResult.show" x-cloak x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-2" class="fixed bottom-4 right-4 z-50">
            <div :class="testResult.success ? 'bg-green-500' : 'bg-red-500'"
                class="text-white px-6 py-4 rounded-lg shadow-lg max-w-md">
                <div class="flex items-center">
                    <svg x-show="testResult.success" class="w-6 h-6 mr-3" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg x-show="!testResult.success" class="w-6 h-6 mr-3" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span x-text="testResult.message" class="font-medium"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function ldapManager() {
            return {
                showAddModal: false,
                showEditModal: false,
                showRoleMappingModal: false,
                selectedConfigId: null,
                editingConfig: {},
                testing: null,
                testingForm: false,
                testResult: {
                    show: false,
                    success: false,
                    message: ''
                },
                formData: {
                    name: 'PHKL',
                    server_url: 'ldaps://MYW02PPL003:636',
                    ip_address: '192.168.17.11',
                    port: 636,
                    use_ssl: true,
                    bind_dn: 'CN=MYW02_SMARTWARDSVC,OU=Service Accounts,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM',
                    bind_password: 'Nickelbook44^145',
                    base_dn: 'OU=Users,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM',
                    filter_string: '(&(objectCategory=Person)(sAMAccountName=*)(memberOf=CN=MY-PHKL-02-smart_ward,OU=Security Groups,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM))',
                    username_attribute: 'sAMAccountName',
                    email_attribute: 'mail',
                    name_attribute: 'displayName',
                    is_active: true,
                    is_default: true
                },

                closeModals() {
                    this.showAddModal = false;
                    this.showEditModal = false;
                    this.resetForm();
                },

                resetForm() {
                    this.formData = {
                        name: 'PHKL',
                        server_url: 'ldaps://MYW02PPL003:636',
                        ip_address: '192.168.17.11',
                        port: 636,
                        use_ssl: true,
                        bind_dn: 'CN=MYW02_SMARTWARDSVC,OU=Service Accounts,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM',
                        bind_password: 'Nickelbook44^145',
                        base_dn: 'OU=Users,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM',
                        filter_string: '(&(objectCategory=Person)(sAMAccountName=*)(memberOf=CN=MY-PHKL-02-smart_ward,OU=Security Groups,OU=MY-PHKL-02,OU=Managed Sites,OU=Malaysia,DC=PPL,DC=IHH,DC=COM))',
                        username_attribute: 'sAMAccountName',
                        email_attribute: 'mail',
                        name_attribute: 'displayName',
                        is_active: true,
                        is_default: true
                    };
                    this.editingConfig = {};
                },

                editConfig(config) {
                    this.editingConfig = config;
                    this.formData = {
                        name: config.name,
                        server_url: config.server_url,
                        ip_address: config.ip_address || '',
                        port: config.port,
                        use_ssl: config.use_ssl,
                        bind_dn: config.bind_dn,
                        bind_password: '',
                        base_dn: config.base_dn,
                        filter_string: config.filter_string || '',
                        username_attribute: config.username_attribute,
                        email_attribute: config.email_attribute,
                        name_attribute: config.name_attribute,
                        is_active: config.is_active,
                        is_default: config.is_default
                    };
                    this.showEditModal = true;
                },

                async testConnection(configId) {
                    this.testing = configId;
                    try {
                        const response = await fetch('{{ route('ldap.test') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ config_id: configId })
                        });
                        const data = await response.json();
                        this.showTestResult(data.success, data.message);
                    } catch (error) {
                        this.showTestResult(false, 'Failed to test connection: ' + error.message);
                    } finally {
                        this.testing = null;
                    }
                },

                async testConnectionFromForm() {
                    this.testingForm = true;
                    try {
                        const payload = {
                            ...this.formData,
                            config_id: this.editingConfig.id || null
                        };
                        const response = await fetch('{{ route('ldap.test') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(payload)
                        });
                        const data = await response.json();
                        this.showTestResult(data.success, data.message);
                    } catch (error) {
                        this.showTestResult(false, 'Failed to test connection: ' + error.message);
                    } finally {
                        this.testingForm = false;
                    }
                },

                showTestResult(success, message) {
                    this.testResult = { show: true, success, message };
                    setTimeout(() => {
                        this.testResult.show = false;
                    }, 5000);
                }
            };
        }
    </script>
</x-app-layout>