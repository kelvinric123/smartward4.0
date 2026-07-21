{{-- Shared modals + toast. Rendered inside the vitalSignIntegration() Alpine scope. --}}

<!-- Add/Edit User Modal -->
<div x-show="showAddUserModal || showEditUserModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
    x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModals()"></div>

        <div class="relative inline-block w-full max-w-lg p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800" x-text="showEditUserModal ? 'Edit API User' : 'Add API User'"></h3>
                <button @click="closeModals()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form :action="showEditUserModal ? '/vital-sign-integration/api-user/' + editingUser.id : '/vital-sign-integration/api-user'" method="POST">
                @csrf
                <template x-if="showEditUserModal">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-1.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h2m10 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                Name * <span class="text-xs font-normal text-gray-400 ml-1">(Scannable)</span>
                            </div>
                        </label>
                        <input type="text" name="name" x-model="userFormData.name" required
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="e.g., GW-001">
                        <p class="mt-1 text-xs text-gray-500">This name is used for barcode/QR code scanning when binding gateways.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                        <input type="text" name="username" x-model="userFormData.username" required
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="e.g., gateway_user_1">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Password <span x-show="!showEditUserModal">*</span>
                            <span x-show="showEditUserModal" class="text-gray-400 font-normal">(leave empty to keep current)</span>
                        </label>
                        <input type="password" name="password" x-model="userFormData.password" :required="!showEditUserModal"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="Minimum 8 characters">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" x-model="userFormData.description" rows="2"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="Optional description for this API user"></textarea>
                    </div>

                    <div x-show="showEditUserModal">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" x-model="userFormData.is_active"
                                class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" @click="closeModals()"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-colors">
                        <span x-text="showEditUserModal ? 'Update User' : 'Create User'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Gateway Modal -->
<div x-show="showAddGatewayModal || showEditGatewayModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
    x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeGatewayModals()"></div>

        <div class="relative inline-block w-full max-w-lg p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800" x-text="showEditGatewayModal ? 'Edit Qmed Gateway' : 'Add Qmed Gateway'"></h3>
                <button @click="closeGatewayModals()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form :action="showEditGatewayModal ? '{{ url('vital-sign-integration/gateway') }}/' + editingGateway.id : '{{ route('vital-sign-integration.gateway.store') }}'" method="POST">
                @csrf
                <template x-if="showEditGatewayModal">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gateway Name *</label>
                        <input type="text" name="name" x-model="gatewayFormData.name" required
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="e.g., Qmed Gateway 1">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" name="location" x-model="gatewayFormData.location"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="e.g., Ward A">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">MAC Address (Optional)</label>
                        <input type="text" name="mac_address" x-model="gatewayFormData.mac_address"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            placeholder="00:11:22:33:44:55">
                        <p class="text-xs text-gray-500 mt-1">Used for automatic identification.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Linked API Users</label>
                        <div class="space-y-2 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3">
                            @foreach($apiUsers as $user)
                                <label class="flex items-center">
                                    <input type="checkbox" name="api_users[]" value="{{ $user->id }}"
                                        x-model="gatewayFormData.api_users"
                                        class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                    <span class="ml-2 text-sm text-gray-700">{{ $user->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Select API users that rely on this gateway.</p>
                    </div>

                    <div x-show="showEditGatewayModal">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" x-model="gatewayFormData.is_active"
                                class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" @click="closeGatewayModals()"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-colors">
                        <span x-text="showEditGatewayModal ? 'Update Gateway' : 'Add Gateway'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div x-show="toast.show" x-cloak x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform translate-y-2"
    x-transition:enter-end="opacity-100 transform translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 transform translate-y-0"
    x-transition:leave-end="opacity-0 transform translate-y-2" class="fixed bottom-4 right-4 z-50">
    <div :class="toast.success ? 'bg-green-500' : 'bg-red-500'" class="text-white px-6 py-4 rounded-lg shadow-lg max-w-md">
        <div class="flex items-center">
            <svg x-show="toast.success" class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg x-show="!toast.success" class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-text="toast.message" class="font-medium"></span>
        </div>
    </div>
</div>
