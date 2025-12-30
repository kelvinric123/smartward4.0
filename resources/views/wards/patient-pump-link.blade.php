<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Link Pump to Patient</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="p-4" x-data="pumpLinkManager()">
        @if($patient)
            <!-- Patient Info Header -->
            <div class="bg-gradient-to-r from-purple-500 to-indigo-600 rounded-xl p-4 mb-4 text-white">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg mr-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold">{{ $patient->name }}</h2>
                        <p class="text-white/80 text-sm">MRN: {{ $patient->mrn }} | {{ $patient->gender ?? 'N/A' }}, {{ $patient->age ?? 'N/A' }} y/o</p>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <div x-show="message" x-transition class="mb-4">
                <div :class="messageType === 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'" 
                     class="border-l-4 px-4 py-3 rounded-lg">
                    <p class="font-medium" x-text="message"></p>
                </div>
            </div>

            <!-- Linked Pumps Section -->
            <div class="bg-white rounded-xl shadow-md border border-gray-200 mb-4">
                <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-500 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Linked Pumps</h3>
                            <p class="text-xs text-gray-500">Pumps currently assigned to this patient</p>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    @if($linkedPumps->count() > 0)
                        <div class="space-y-3">
                            @foreach($linkedPumps as $pump)
                                <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-green-100 rounded-lg mr-3">
                                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-800">{{ $pump->device_id }}</p>
                                            <p class="text-xs text-gray-600">
                                                {{ $pump->device_name ?? 'Unnamed Pump' }}
                                                @if($pump->device_type) • {{ ucfirst(str_replace('_', ' ', $pump->device_type)) }} @endif
                                            </p>
                                            @if($pump->linked_at)
                                                <p class="text-xs text-green-600">Linked {{ $pump->linked_at->diffForHumans() }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <button 
                                        @click="unlinkPump({{ $pump->id }})"
                                        :disabled="loading"
                                        class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Unlink
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-500 text-sm">
                            No pumps linked to this patient. Select a pump below or scan a pump ID.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Available Pumps Section -->
            <div class="bg-white rounded-xl shadow-md border border-gray-200">
                <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-500 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Available Pumps</h3>
                            <p class="text-xs text-gray-500">Select a pump to link to this patient</p>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <!-- Scan Pump ID Button -->
                    <div class="mb-4">
                        <div class="flex items-center space-x-2">
                            <input type="text" 
                                   x-model="scannedPumpId"
                                   @keydown.enter="linkByDeviceId()"
                                   placeholder="Scan or enter Pump ID..."
                                   class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                   :disabled="loading">
                            <button 
                                @click="linkByDeviceId()"
                                :disabled="loading || !scannedPumpId"
                                class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                                </svg>
                                Link by ID
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Use a barcode scanner or manually enter the pump's device ID</p>
                    </div>

                    @if($availablePumps->count() > 0)
                        <div class="space-y-2">
                            @foreach($availablePumps as $pump)
                                <div class="flex items-center justify-between p-3 bg-gray-50 hover:bg-blue-50 border border-gray-200 hover:border-blue-300 rounded-lg transition-all cursor-pointer group">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-gray-200 group-hover:bg-blue-100 rounded-lg mr-3 transition-colors">
                                            <svg class="w-5 h-5 text-gray-600 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-800">{{ $pump->device_id }}</p>
                                            <p class="text-xs text-gray-600">
                                                {{ $pump->device_name ?? 'Unnamed Pump' }}
                                                @if($pump->device_type) | {{ ucfirst(str_replace('_', ' ', $pump->device_type)) }} @endif
                                                @if($pump->location) | {{ $pump->location }} @endif
                                            </p>
                                            @if($pump->last_seen_at)
                                                <p class="text-xs text-gray-500">Last active: {{ $pump->last_seen_at->diffForHumans() }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <button 
                                        @click="linkPump({{ $pump->id }})"
                                        :disabled="loading"
                                        class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                        </svg>
                                        Link
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-500 text-sm">
                            No available pumps. All pumps are currently linked to patients or inactive.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Info Box -->
            <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-amber-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm text-amber-700">
                        <strong>How it works:</strong> When a pump is linked to a patient, all HL7 messages received from that pump will be automatically associated with this patient. This is useful when the pump doesn't send patient MRN in its messages.
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-gray-500 text-lg">Patient not found</p>
                <p class="text-gray-400 text-sm mt-1">Unable to load patient information.</p>
            </div>
        @endif
    </div>

    <script>
        function pumpLinkManager() {
            return {
                loading: false,
                message: '',
                messageType: 'success',
                patientId: {{ $patient->id ?? 'null' }},
                scannedPumpId: '',

                async linkByDeviceId() {
                    if (this.loading || !this.patientId || !this.scannedPumpId.trim()) return;
                    
                    this.loading = true;
                    this.message = '';

                    try {
                        const response = await fetch('{{ route("ward.link-pump-by-device-id") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                patient_id: this.patientId,
                                device_id: this.scannedPumpId.trim()
                            })
                        });

                        const data = await response.json();
                        
                        if (data.success) {
                            this.message = data.message;
                            this.messageType = 'success';
                            this.scannedPumpId = '';
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            this.message = data.message || 'Failed to link pump';
                            this.messageType = 'error';
                        }
                    } catch (error) {
                        this.message = 'An error occurred. Please try again.';
                        this.messageType = 'error';
                    }

                    this.loading = false;
                },

                async linkPump(pumpId) {
                    if (this.loading || !this.patientId) return;
                    
                    this.loading = true;
                    this.message = '';

                    try {
                        const response = await fetch('{{ route("ward.link-pump") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                patient_id: this.patientId,
                                pump_id: pumpId
                            })
                        });

                        const data = await response.json();
                        
                        if (data.success) {
                            this.message = data.message;
                            this.messageType = 'success';
                            // Reload the page to show updated lists
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            this.message = data.message || 'Failed to link pump';
                            this.messageType = 'error';
                        }
                    } catch (error) {
                        this.message = 'An error occurred. Please try again.';
                        this.messageType = 'error';
                    }

                    this.loading = false;
                },

                async unlinkPump(pumpId) {
                    if (this.loading) return;
                    
                    if (!confirm('Are you sure you want to unlink this pump from the patient?')) {
                        return;
                    }

                    this.loading = true;
                    this.message = '';

                    try {
                        const response = await fetch(`{{ url('ward-dashboard/unlink-pump') }}/${pumpId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            }
                        });

                        const data = await response.json();
                        
                        if (data.success) {
                            this.message = data.message;
                            this.messageType = 'success';
                            // Reload the page to show updated lists
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            this.message = data.message || 'Failed to unlink pump';
                            this.messageType = 'error';
                        }
                    } catch (error) {
                        this.message = 'An error occurred. Please try again.';
                        this.messageType = 'error';
                    }

                    this.loading = false;
                }
            };
        }
    </script>
</body>
</html>


