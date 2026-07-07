<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Vital Sign Integration') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Receive vital sign readings from Gateway devices via RESTful API</p>
            </div>
            <button onclick="document.getElementById('apiInfoModal').classList.remove('hidden')"
                class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                API Documentation
            </button>
        </div>
    </x-slot>

    @include('integration.vital-sign.partials.api-docs-modal')

    <div class="py-8" x-data="vitalSignIntegration()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <div class="flex items-center mb-2">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-bold">Please check the form for errors:</span>
                    </div>
                    <ul class="list-disc list-inside text-sm pl-8">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ================= Tab Navigation ================= --}}
            <div class="bg-white/90 backdrop-blur-sm shadow-lg rounded-2xl border border-gray-100 p-2">
                <nav class="flex flex-wrap gap-1" aria-label="Sections">
                    @php
                        $tabs = [
                            ['id' => 'gateways', 'label' => 'Qmed Gateways', 'count' => $gateways->count(),
                                'icon' => 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z'],
                            ['id' => 'users', 'label' => 'API Users', 'count' => $apiUsers->count(),
                                'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'],
                            ['id' => 'config', 'label' => 'Configuration', 'count' => null,
                                'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
                            ['id' => 'testing', 'label' => 'API Testing', 'count' => null,
                                'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                            ['id' => 'logs', 'label' => 'API Logs', 'count' => $recentLogs->count(),
                                'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ];
                    @endphp
                    @foreach($tabs as $tab)
                        <button type="button" @click="setTab('{{ $tab['id'] }}')"
                            :class="mainTab === '{{ $tab['id'] }}' ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100'"
                            class="flex-1 min-w-[9rem] inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-sm font-semibold transition-all">
                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}" />
                            </svg>
                            {{ $tab['label'] }}
                            @if(!is_null($tab['count']))
                                <span :class="mainTab === '{{ $tab['id'] }}' ? 'bg-white/25 text-white' : 'bg-gray-200 text-gray-600'"
                                    class="ml-2 px-1.5 py-0.5 text-xs font-bold rounded-full">{{ $tab['count'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>

            {{-- ================= Tab Panels ================= --}}
            <div x-show="mainTab === 'gateways'" x-cloak>
                @include('integration.vital-sign.partials.tab-gateways')
            </div>

            <div x-show="mainTab === 'users'" x-cloak>
                @include('integration.vital-sign.partials.tab-users')
            </div>

            <div x-show="mainTab === 'config'" x-cloak>
                @include('integration.vital-sign.partials.tab-config')
            </div>

            <div x-show="mainTab === 'testing'" x-cloak>
                @include('integration.vital-sign.partials.tab-testing')
            </div>

            <div x-show="mainTab === 'logs'" x-cloak>
                @include('integration.vital-sign.partials.tab-logs')
            </div>
        </div>

        @include('integration.vital-sign.partials.modals')
    </div>

    <script>
        // Global helper functions
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.createElement('div');
                toast.className = 'fixed bottom-4 right-4 z-50 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg';
                toast.textContent = 'Copied to clipboard!';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
            });
        }

        function copyGatewayConfig() {
            const configText = `# Laravel API Configuration
API_BASE_URL = "http://{{ $gatewayConfig['server_ip'] }}:{{ $gatewayConfig['server_port'] }}/api/v1"

# API Passphrase (must match Laravel configuration)
API_PASSPHRASE = "{{ $gatewayConfig['passphrase'] }}"

# Device Credentials (create an API User in Laravel)
DEVICE_USERNAME = "your_api_user@example.com"
DEVICE_PASSWORD = "your_password"`;
            copyToClipboard(configText);
        }

        function vitalSignIntegration() {
            return {
                // Layout state
                mainTab: @json(($isFiltered ?? false) ? 'logs' : 'gateways'),
                gwView: 'panel', // 'panel' (control panel) | 'details'

                // User modal state
                showAddUserModal: false,
                showEditUserModal: false,
                showPassphrase: false,
                editingUser: {},
                userFormData: {
                    name: '',
                    username: '',
                    password: '',
                    description: '',
                    is_active: true
                },

                // Gateway modal state
                showAddGatewayModal: false,
                showEditGatewayModal: false,
                editingGateway: {},
                gatewayFormData: {
                    name: '',
                    location: '',
                    mac_address: '',
                    is_active: true,
                    api_users: []
                },

                // Testing state
                testLogin: {
                    username: '',
                    password: ''
                },
                testVital: {
                    patient_mrn: '',
                    patient_rn: '',
                    systolic_bp: '',
                    diastolic_bp: '',
                    pulse_rate: '',
                    temperature: '',
                    spo2: '',
                    respiratory_rate: ''
                },
                bearerToken: '',
                apiResponse: '',
                loginLoading: false,
                vitalLoading: false,
                toast: {
                    show: false,
                    success: false,
                    message: ''
                },

                init() {
                    // Restore the last-used tab/view (unless a server-side log filter is active).
                    @if(!($isFiltered ?? false))
                        try {
                            const savedTab = localStorage.getItem('vsi_main_tab');
                            if (savedTab) this.mainTab = savedTab;
                        } catch (e) {}
                    @endif
                    try {
                        const savedView = localStorage.getItem('vsi_gw_view');
                        if (savedView) this.gwView = savedView;
                    } catch (e) {}

                    @if(session('success'))
                        this.showToast(true, @json(session('success')));
                    @endif
                    @if(session('error'))
                        this.showToast(false, @json(session('error')));
                    @endif
                    @if($errors->any())
                        this.showToast(false, @json($errors->first()));
                    @endif
                },

                // Layout methods
                setTab(tab) {
                    this.mainTab = tab;
                    try { localStorage.setItem('vsi_main_tab', tab); } catch (e) {}
                },
                setGwView(view) {
                    this.gwView = view;
                    try { localStorage.setItem('vsi_gw_view', view); } catch (e) {}
                },

                // User modal methods
                closeModals() {
                    this.showAddUserModal = false;
                    this.showEditUserModal = false;
                    this.resetUserForm();
                },

                resetUserForm() {
                    this.userFormData = {
                        name: '',
                        username: '',
                        password: '',
                        description: '',
                        is_active: true
                    };
                    this.editingUser = {};
                },

                populateUser(user) {
                    this.editingUser = user;
                    this.userFormData = {
                        name: user.name,
                        username: user.username,
                        password: '', // Don't populate password
                        description: user.description,
                        is_active: user.is_active
                    };
                    this.showEditUserModal = true;
                },

                // Alias kept for the API Users "Edit" button.
                editUser(user) {
                    this.populateUser(user);
                },

                // Gateway modal methods
                openAddGatewayModal() {
                    this.resetGatewayForm();
                    this.showAddGatewayModal = true;
                },

                editGateway(gateway, apiUsers) {
                    this.editingGateway = gateway;
                    this.gatewayFormData = {
                        name: gateway.name,
                        location: gateway.location,
                        mac_address: gateway.mac_address,
                        is_active: gateway.is_active,
                        api_users: apiUsers
                    };
                    this.showEditGatewayModal = true;
                },

                closeGatewayModals() {
                    this.showAddGatewayModal = false;
                    this.showEditGatewayModal = false;
                    this.resetGatewayForm();
                },

                resetGatewayForm() {
                    this.gatewayFormData = {
                        name: '',
                        location: '',
                        mac_address: '',
                        is_active: true,
                        api_users: []
                    };
                    this.editingGateway = {};
                },

                async performLogin() {
                    if (!this.testLogin.username || !this.testLogin.password) {
                        this.showToast(false, 'Please enter username and password');
                        return;
                    }

                    this.loginLoading = true;
                    this.apiResponse = 'Sending login request...';

                    try {
                        const response = await fetch('/api/vital-sign/login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                username: this.testLogin.username,
                                password: this.testLogin.password
                            })
                        });

                        const data = await response.json();
                        this.apiResponse = JSON.stringify(data, null, 2);

                        if (data.success && data.data && data.data.token) {
                            this.bearerToken = data.data.token;
                            this.showToast(true, 'Login successful! Token received.');
                        } else {
                            this.showToast(false, data.message || 'Login failed');
                        }
                    } catch (error) {
                        this.apiResponse = 'Error: ' + error.message;
                        this.showToast(false, 'Connection error: ' + error.message);
                    } finally {
                        this.loginLoading = false;
                    }
                },

                async sendVitalSign() {
                    if (!this.bearerToken) {
                        this.showToast(false, 'Please login first to get a bearer token');
                        return;
                    }

                    if (!this.testVital.patient_mrn && !this.testVital.patient_rn) {
                        this.showToast(false, 'Patient MRN or RN is required');
                        return;
                    }

                    this.vitalLoading = true;
                    this.apiResponse = 'Sending vital sign reading...';

                    try {
                        const payload = {};

                        if (this.testVital.patient_mrn) payload.patient_mrn = this.testVital.patient_mrn;
                        if (this.testVital.patient_rn) payload.patient_rn = this.testVital.patient_rn;

                        // Only include non-empty values
                        if (this.testVital.systolic_bp) payload.systolic_bp = parseInt(this.testVital.systolic_bp);
                        if (this.testVital.diastolic_bp) payload.diastolic_bp = parseInt(this.testVital.diastolic_bp);
                        if (this.testVital.pulse_rate) payload.pulse_rate = parseInt(this.testVital.pulse_rate);
                        if (this.testVital.temperature) payload.temperature = parseFloat(this.testVital.temperature);
                        if (this.testVital.spo2) payload.spo2 = parseInt(this.testVital.spo2);
                        if (this.testVital.respiratory_rate) payload.respiratory_rate = parseInt(this.testVital.respiratory_rate);

                        const response = await fetch('/api/vital-sign/reading', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'Authorization': 'Bearer ' + this.bearerToken
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await response.json();
                        this.apiResponse = JSON.stringify(data, null, 2);

                        if (data.success) {
                            this.showToast(true, 'Vital sign recorded successfully!');
                        } else {
                            this.showToast(false, data.message || 'Failed to record vital sign');
                        }
                    } catch (error) {
                        this.apiResponse = 'Error: ' + error.message;
                        this.showToast(false, 'Connection error: ' + error.message);
                    } finally {
                        this.vitalLoading = false;
                    }
                },

                copyToken() {
                    navigator.clipboard.writeText(this.bearerToken);
                    this.showToast(true, 'Token copied to clipboard!');
                },

                showToast(success, message) {
                    this.toast = { show: true, success, message };
                    setTimeout(() => {
                        this.toast.show = false;
                    }, 4000);
                }
            };
        }

        // API Logs Component
        function apiLogsComponent() {
            return {
                categoryFilter: 'all',
                showDetailsModal: false,
                selectedLog: null,
                activeTab: 'request',

                matchesFilter(endpoint) {
                    if (this.categoryFilter === 'all') return true;

                    const endpointLower = endpoint.toLowerCase();

                    switch (this.categoryFilter) {
                        case 'vital-signs':
                            return endpointLower.includes('vital-sign') || endpointLower.includes('vital_sign');
                        case 'ping':
                            return endpointLower.includes('ping');
                        case 'login':
                            return endpointLower.includes('login') || endpointLower.includes('logout');
                        case 'patients':
                            return endpointLower.includes('patient');
                        case 'monitor':
                            return endpointLower.includes('monitor') || endpointLower.includes('device');
                        default:
                            return true;
                    }
                },

                showLogDetails(log) {
                    this.selectedLog = log;
                    this.activeTab = log.debug_data ? 'debug' : 'request';
                    this.showDetailsModal = true;
                }
            };
        }
    </script>

</x-app-layout>
