<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADT Mappings</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">ADT Mappings</h2>
                <p class="text-sm text-gray-500">Manage mapping between HIS codes and SmartWard</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Hospital Mapping --}}
            <div class="bg-white shadow rounded-2xl border border-orange-100">
                <div class="p-4 border-b border-orange-100 bg-gradient-to-r from-orange-50 to-amber-50 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="p-2 bg-orange-500 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                            </svg>
                        </div>
                        <h3 class="text-md font-bold text-gray-800">Hospital Mapping</h3>
                    </div>
                </div>
                <div class="p-4 space-y-4">
                    <form action="{{ route('adt.hospital-mapping.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Hospital Code *</label>
                            <input type="text" name="adt_hospital_code" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Hospital Name</label>
                            <input type="text" name="adt_hospital_name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Map to Hospital *</label>
                            <select name="hospital_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                <option value="">Select hospital...</option>
                                @foreach($hospitals as $hospital)
                                    <option value="{{ $hospital->id }}">{{ $hospital->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">Add</button>
                        </div>
                    </form>
                    <div class="max-h-56 overflow-y-auto space-y-2">
                        @forelse($hospitalMappings as $mapping)
                            <div class="p-3 bg-gray-50 rounded-lg flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $mapping->adt_hospital_code }}</p>
                                    <p class="text-xs text-gray-500 truncate">→ {{ $mapping->hospital->name }}</p>
                                </div>
                                <form action="{{ route('adt.hospital-mapping.destroy', $mapping) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700" onclick="return confirm('Delete?')">✕</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-4">No hospital mappings</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Ward Mapping --}}
            <div class="bg-white shadow rounded-2xl border border-blue-100">
                <div class="p-4 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-indigo-50 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-500 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-md font-bold text-gray-800">Ward Mapping</h3>
                    </div>
                </div>
                <div class="p-4 space-y-4">
                    <form action="{{ route('adt.ward-mapping.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Ward Code *</label>
                            <input type="text" name="adt_ward_code" required placeholder="e.g., WWD6" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Ward Name</label>
                            <input type="text" name="adt_ward_name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Map to Ward *</label>
                            <select name="ward_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select ward...</option>
                                @foreach($wards as $ward)
                                    <option value="{{ $ward->id }}">{{ $ward->ward_name }} ({{ $ward->hospital->name ?? 'N/A' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Add</button>
                        </div>
                    </form>
                    <div class="max-h-56 overflow-y-auto space-y-2">
                        @forelse($wardMappings as $mapping)
                            <div class="p-3 bg-gray-50 rounded-lg flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $mapping->adt_ward_code }}</p>
                                    <p class="text-xs text-gray-500 truncate">→ {{ $mapping->ward->ward_name }} ({{ $mapping->ward->hospital->name ?? 'N/A' }})</p>
                                </div>
                                <form action="{{ route('adt.ward-mapping.destroy', $mapping) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700" onclick="return confirm('Delete?')">✕</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-4">No ward mappings</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Bed Mapping --}}
            <div class="bg-white shadow rounded-2xl border border-green-100">
                <div class="p-4 border-b border-green-100 bg-gradient-to-r from-green-50 to-emerald-50 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-500 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                        </div>
                        <h3 class="text-md font-bold text-gray-800">Bed Mapping</h3>
                    </div>
                </div>
                <div class="p-4 space-y-4">
                    <form action="{{ route('adt.bed-mapping.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Bed Code *</label>
                            <input type="text" name="adt_bed_code" required placeholder="e.g., D610" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Bed Name</label>
                            <input type="text" name="adt_bed_name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Map to Bed *</label>
                            <select name="bed_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                <option value="">Select bed...</option>
                                @foreach($beds as $bed)
                                    <option value="{{ $bed->id }}">{{ $bed->bed_number }} - {{ $bed->ward->ward_name ?? 'N/A' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">Add</button>
                        </div>
                    </form>
                    <div class="max-h-56 overflow-y-auto space-y-2">
                        @forelse($bedMappings as $mapping)
                            <div class="p-3 bg-gray-50 rounded-lg flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $mapping->adt_bed_code }}</p>
                                    <p class="text-xs text-gray-500 truncate">→ {{ $mapping->bed->bed_number }} ({{ $mapping->bed->ward->ward_name ?? 'N/A' }})</p>
                                </div>
                                <form action="{{ route('adt.bed-mapping.destroy', $mapping) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700" onclick="return confirm('Delete?')">✕</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-4">No bed mappings</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Doctor Mapping --}}
            <div class="bg-white shadow rounded-2xl border border-purple-100">
                <div class="p-4 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-violet-50 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-500 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-md font-bold text-gray-800">Doctor Mapping</h3>
                    </div>
                </div>
                <div class="p-4 space-y-4">
                    <form action="{{ route('adt.doctor-mapping.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Doctor Code *</label>
                            <input type="text" name="adt_doctor_code" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Doctor Name</label>
                            <input type="text" name="adt_doctor_name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Doctor Type *</label>
                            <select name="doctor_type" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                @foreach($doctorTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Map to Consultant *</label>
                            <select name="consultant_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                <option value="">Select consultant...</option>
                                @foreach($consultants as $consultant)
                                    <option value="{{ $consultant->id }}">{{ $consultant->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600">Add</button>
                        </div>
                    </form>
                    <div class="max-h-56 overflow-y-auto space-y-2">
                        @forelse($doctorMappings as $mapping)
                            <div class="p-3 bg-gray-50 rounded-lg flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $mapping->adt_doctor_code }} ({{ ucfirst($mapping->doctor_type) }})</p>
                                    <p class="text-xs text-gray-500 truncate">→ {{ $mapping->consultant->name ?? 'N/A' }}</p>
                                </div>
                                <form action="{{ route('adt.doctor-mapping.destroy', $mapping) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700" onclick="return confirm('Delete?')">✕</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-4">No doctor mappings</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Diet Mapping --}}
            <div class="bg-white shadow rounded-2xl border border-amber-100">
                <div class="p-4 border-b border-amber-100 bg-gradient-to-r from-amber-50 to-yellow-50 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="p-2 bg-amber-500 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.567-3 3.5S10.343 15 12 15s3-1.567 3-3.5S13.657 8 12 8z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m-7-7h1m12 0h1M5.636 6.636l.707.707m10.314 10.314l.707.707M5.636 17.364l.707-.707m10.314-10.314l.707-.707"/>
                            </svg>
                        </div>
                        <h3 class="text-md font-bold text-gray-800">Diet Mapping</h3>
                    </div>
                </div>
                <div class="p-4 space-y-4">
                    <form action="{{ route('adt.diet-mapping.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Diet Code *</label>
                            <input type="text" name="adt_diet_code" required placeholder="e.g., DMD, REGD" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Diet Name</label>
                            <input type="text" name="adt_diet_name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Map to Diet</label>
                            <input type="text" name="mapped_diet" placeholder="e.g., diabetic, regular" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600">Add</button>
                        </div>
                    </form>
                    <div class="max-h-56 overflow-y-auto space-y-2">
                        @forelse($dietMappings as $mapping)
                            <div class="p-3 bg-gray-50 rounded-lg flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $mapping->adt_diet_code }}</p>
                                    <p class="text-xs text-gray-500 truncate">→ {{ $mapping->mapped_diet ?? 'N/A' }}</p>
                                </div>
                                <form action="{{ route('adt.diet-mapping.destroy', $mapping) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700" onclick="return confirm('Delete?')">✕</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-4">No diet mappings</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Isolation Mapping --}}
            <div class="bg-white shadow rounded-2xl border border-teal-100">
                <div class="p-4 border-b border-teal-100 bg-gradient-to-r from-teal-50 to-cyan-50 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="p-2 bg-teal-500 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <h3 class="text-md font-bold text-gray-800">Isolation Mapping</h3>
                    </div>
                </div>
                <div class="p-4 space-y-4">
                    <form action="{{ route('adt.isolation-mapping.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Isolation Code *</label>
                            <input type="text" name="adt_isolation_code" required placeholder="e.g., CI, AI, DC" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ADT Isolation Name</label>
                            <input type="text" name="adt_isolation_name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Map to Isolation</label>
                            <input type="text" name="mapped_isolation" placeholder="e.g., contact, airborne, droplet" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600">Add</button>
                        </div>
                    </form>
                    <div class="max-h-56 overflow-y-auto space-y-2">
                        @forelse($isolationMappings as $mapping)
                            <div class="p-3 bg-gray-50 rounded-lg flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $mapping->adt_isolation_code }}</p>
                                    <p class="text-xs text-gray-500 truncate">→ {{ $mapping->mapped_isolation ?? 'N/A' }}</p>
                                </div>
                                <form action="{{ route('adt.isolation-mapping.destroy', $mapping) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700" onclick="return confirm('Delete?')">✕</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-4">No isolation mappings</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>





